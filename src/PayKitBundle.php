<?php

declare(strict_types=1);

namespace PayKit\Bundle;

use PayKit\Bundle\Catalog\Catalog;
use PayKit\Bundle\Command\PushCommand;
use PayKit\Bundle\Command\StatusCommand;
use PayKit\Bundle\Controller\ApiController;
use PayKit\Bundle\Controller\WebhookController;
use PayKit\Bundle\Provider\PaymentProviderInterface;
use PayKit\Bundle\Provider\Stripe\StripeProvider;
use PayKit\Bundle\Repository\BillingRepository;
use PayKit\Bundle\Security\CustomerResolverInterface;
use PayKit\Bundle\Security\FeatureVoter;
use PayKit\Bundle\Security\RequiresFeatureListener;
use PayKit\Bundle\Security\SecurityUserCustomerResolver;
use PayKit\Bundle\Service\CustomerService;
use PayKit\Bundle\Service\EntitlementService;
use PayKit\Bundle\Service\EventQueue;
use PayKit\Bundle\Service\ProductSyncService;
use PayKit\Bundle\Service\ProviderCustomerLinker;
use PayKit\Bundle\Service\SubscriptionService;
use PayKit\Bundle\Service\WebhookProcessor;
use PayKit\Bundle\Testing\FakeProvider;
use PayKit\Bundle\Twig\PayKitExtension;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Twig\Environment;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class PayKitBundle extends AbstractBundle
{
    protected string $extensionAlias = 'paykit';

    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var ArrayNodeDefinition $root */
        $root = $definition->rootNode();
        $root
            ->children()
                ->enumNode('provider')->values(['stripe', 'fake'])->defaultValue('stripe')
                    ->info('"fake" uses the in-memory provider (tests / offline dev). Override PaymentProviderInterface for custom providers.')->end()
                ->scalarNode('currency')->defaultValue('usd')->end()
                ->arrayNode('stripe')->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('secret_key')->defaultValue('%env(default::STRIPE_SECRET_KEY)%')->end()
                        ->scalarNode('webhook_secret')->defaultValue('%env(default::STRIPE_WEBHOOK_SECRET)%')->end()
                    ->end()
                ->end()
                ->arrayNode('trusted_origins')->scalarPrototype()->end()->end()
                ->scalarNode('customer_resolver')->defaultNull()
                    ->info('Service id implementing CustomerResolverInterface (default: security user identifier).')->end()
                ->arrayNode('features')
                    ->useAttributeAsKey('id')
                    ->arrayPrototype()
                        ->beforeNormalization()->ifString()->then(static fn (string $v): array => ['type' => $v])->end()
                        ->children()
                            ->enumNode('type')->values(['boolean', 'metered'])->isRequired()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('plans')
                    ->useAttributeAsKey('id')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')->defaultNull()->end()
                            ->scalarNode('group')->defaultValue('default')->end()
                            ->booleanNode('default')->defaultFalse()->end()
                            ->integerNode('trial_days')->defaultNull()->min(1)->end()
                            ->arrayNode('price')
                                ->children()
                                    ->floatNode('amount')->isRequired()->min(0.01)->end()
                                    ->enumNode('interval')->values(['month', 'year'])->isRequired()->end()
                                ->end()
                            ->end()
                            ->arrayNode('includes')
                                ->useAttributeAsKey('feature')
                                ->arrayPrototype()
                                    ->beforeNormalization()->ifNull()->then(static fn (): array => [])->end()
                                    ->children()
                                        ->integerNode('limit')->defaultNull()->end()
                                        ->enumNode('reset')->values(['day', 'week', 'month', 'year'])->defaultNull()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $plans = array_map(static function (array $plan): array {
            if ([] === ($plan['price'] ?? [])) {
                unset($plan['price']);
            }

            return $plan;
        }, $config['plans']);
        // Validate eagerly so configuration mistakes fail at container compile time.
        Catalog::fromConfig($config['features'], $plans, $config['currency']);

        $s = $container->services()->defaults()->private()->autowire()->autoconfigure();

        $s->set(Catalog::class)
            ->factory([Catalog::class, 'fromConfig'])
            ->args([$config['features'], $plans, $config['currency']]);

        if ('fake' === $config['provider']) {
            $s->set(PaymentProviderInterface::class, FakeProvider::class)->public();
        } else {
            $s->set(PaymentProviderInterface::class, StripeProvider::class)
                ->args([$config['stripe']['secret_key'], $config['stripe']['webhook_secret']]);
        }

        $s->set(BillingRepository::class)->args([service('doctrine.orm.entity_manager')]);
        $s->set(EventQueue::class)->args([service(BillingRepository::class), service('event_dispatcher')->nullOnInvalid()]);
        $s->set(ProviderCustomerLinker::class);
        $s->set(EntitlementService::class)->args([service(BillingRepository::class), service(Catalog::class), service('clock'), service('event_dispatcher')->nullOnInvalid()]);
        $s->set(SubscriptionService::class)->arg('$logger', service('logger')->ignoreOnInvalid())->tag('monolog.logger', ['channel' => 'paykit']);
        $s->set(CustomerService::class);
        $s->set(ProductSyncService::class);
        $s->set(WebhookProcessor::class)->arg('$logger', service('logger')->ignoreOnInvalid())->tag('monolog.logger', ['channel' => 'paykit']);
        $s->set(PayKit::class)->public();
        $s->alias('paykit', PayKit::class)->public();

        $resolverId = $config['customer_resolver'] ?? SecurityUserCustomerResolver::class;
        $s->set(SecurityUserCustomerResolver::class)->args([class_exists(Security::class) ? service('security.helper')->nullOnInvalid() : null]);
        $s->alias(CustomerResolverInterface::class, $resolverId);

        $s->set(RequiresFeatureListener::class);
        if (interface_exists(\Symfony\Component\Security\Core\Authorization\Voter\VoterInterface::class)) {
            $s->set(FeatureVoter::class);
        }
        if (class_exists(Environment::class)) {
            $s->set(PayKitExtension::class);
        }

        $s->set(WebhookController::class)->public()->tag('controller.service_arguments');
        $s->set(ApiController::class)->public()->arg('$trustedOrigins', $config['trusted_origins'])->tag('controller.service_arguments');
        $s->set(PushCommand::class);
        $s->set(StatusCommand::class);
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('doctrine', [
                'orm' => ['mappings' => ['PayKitBundle' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => __DIR__.'/Entity',
                    'prefix' => 'PayKit\Bundle\Entity',
                    'alias' => 'PayKit',
                ]]],
            ]);
        }
        if ($builder->hasExtension('doctrine_migrations')) {
            $builder->prependExtensionConfig('doctrine_migrations', [
                'migrations_paths' => ['PayKit\Bundle\Migrations' => \dirname(__DIR__).'/migrations'],
            ]);
        }
    }
}
