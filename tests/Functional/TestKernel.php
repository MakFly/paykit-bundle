<?php

declare(strict_types=1);

namespace PayKit\Bundle\Tests\Functional;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use PayKit\Bundle\PayKitBundle;
use PayKit\Bundle\Security\CustomerResolverInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new PayKitBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/paykit-bundle-tests/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/paykit-bundle-tests/log';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $loader->load(__DIR__.'/config/packages.yaml');
        $container->register(HeaderCustomerResolver::class)->setAutowired(true)->setPublic(true);
        $container->setAlias(CustomerResolverInterface::class, HeaderCustomerResolver::class)->setPublic(true);
        $container->register(GatedController::class)->setAutowired(true)->setPublic(true)->addTag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PayKitBundle/config/routes.php')->prefix('/paykit');
        $routes->add('gated_pro', '/pro')->controller([GatedController::class, 'pro']);
        $routes->add('gated_chat', '/chat')->controller([GatedController::class, 'chat']);
    }
}
