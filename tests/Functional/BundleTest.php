<?php

declare(strict_types=1);

namespace PayKit\Bundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PayKit\Bundle\Entity\PaymentMethod;
use PayKit\Bundle\PayKit;
use PayKit\Bundle\Provider\Dto\ProviderEvent;
use PayKit\Bundle\Provider\PaymentProviderInterface;
use PayKit\Bundle\Testing\FakeProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

final class BundleTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
    }

    public function testServicesAndCatalogAreWired(): void
    {
        $payKit = self::getContainer()->get(PayKit::class);
        self::assertInstanceOf(PayKit::class, $payKit);
        self::assertSame($payKit, self::getContainer()->get('paykit'));
        self::assertSame(1900, $payKit->catalog()->plan('pro')->priceAmount);
        self::assertSame('eur', $payKit->catalog()->plan('pro')->priceCurrency);
        self::assertInstanceOf(FakeProvider::class, self::getContainer()->get(PaymentProviderInterface::class));
    }

    public function testPushAndStatusCommands(): void
    {
        $app = new Application(self::$kernel);

        $status = new CommandTester($app->find('paykit:status'));
        self::assertSame(1, $status->execute(['--throw' => true]));

        $push = new CommandTester($app->find('paykit:push'));
        self::assertSame(0, $push->execute([]));
        self::assertStringContainsString('2 plan(s) synced', $push->getDisplay());

        self::assertSame(0, $status->execute(['--throw' => true]));
        self::assertStringContainsString('in sync', $status->getDisplay());
    }

    public function testRequiresFeatureAttributeAndUsageConsumption(): void
    {
        $this->push();
        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);

        self::assertSame(401, $kernel->handle(Request::create('/pro'))->getStatusCode());

        self::getContainer()->get(PayKit::class)->upsertCustomer('u1');
        self::assertSame(402, $kernel->handle($this->as('u1', '/pro'))->getStatusCode());

        self::assertSame(200, $kernel->handle($this->as('u1', '/chat'))->getStatusCode());
        self::assertSame(200, $kernel->handle($this->as('u1', '/chat'))->getStatusCode());
        self::assertSame(402, $kernel->handle($this->as('u1', '/chat'))->getStatusCode(), 'free plan allows 2 messages');
    }

    public function testApiSubscribeAndWebhookEndpoints(): void
    {
        $this->push();
        $kernel = self::$kernel;

        $response = $kernel->handle($this->as('u2', '/paykit/api/subscribe', 'POST', ['planId' => 'pro', 'successUrl' => '/billing/success']));
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true);
        self::assertSame('checkout', $body['action']);
        self::assertStringStartsWith('https://checkout.fake/', $body['paymentUrl']);

        /** @var FakeProvider $provider */
        $provider = self::getContainer()->get(PaymentProviderInterface::class);
        $sessionId = (string) array_key_last($provider->checkouts);
        self::assertSame('http://localhost/billing/success', $provider->checkouts[$sessionId]->successUrl, 'relative URLs are made absolute');

        $provider->queueWebhook('{"id":"evt"}', new ProviderEvent('evt', ProviderEvent::CHECKOUT_COMPLETED, null, $provider->completeCheckout($sessionId), checkoutSessionId: $sessionId));
        $bad = Request::create('/paykit/webhook', 'POST', content: '{"id":"evt"}');
        self::assertSame(400, $kernel->handle($bad)->getStatusCode());

        $good = Request::create('/paykit/webhook', 'POST', server: ['HTTP_X_FAKE_SIGNATURE' => 'valid'], content: '{"id":"evt"}');
        $response = $kernel->handle($good);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        self::assertSame(200, $kernel->handle($this->as('u2', '/pro'))->getStatusCode());

        $response = $kernel->handle($this->as('u2', '/paykit/api/customer', 'GET'));
        $customer = json_decode((string) $response->getContent(), true);
        self::assertSame('pro', $customer['subscriptions'][0]['planId']);
    }

    public function testApiRejectsMismatchedCustomerAndUntrustedOrigin(): void
    {
        $this->push();
        $kernel = self::$kernel;

        self::assertSame(403, $kernel->handle($this->as('u3', '/paykit/api/subscribe', 'POST', ['planId' => 'pro', 'customerId' => 'someone_else']))->getStatusCode());

        $request = $this->as('u3', '/paykit/api/subscribe', 'POST', ['planId' => 'pro']);
        $request->headers->set('Origin', 'https://evil.example');
        self::assertSame(403, $kernel->handle($request)->getStatusCode());
        self::assertSame(401, $kernel->handle(Request::create('/paykit/api/subscribe', 'POST', content: '{}'))->getStatusCode());
    }

    private function push(): void
    {
        self::getContainer()->get(PayKit::class)->push();
    }

    /** @param array<string, mixed>|null $json */
    private function as(string $user, string $uri, string $method = 'GET', ?array $json = null): Request
    {
        return Request::create($uri, $method, server: ['HTTP_X_USER' => $user, 'CONTENT_TYPE' => 'application/json'], content: null === $json ? null : json_encode($json, \JSON_THROW_ON_ERROR));
    }
}
