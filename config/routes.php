<?php

declare(strict_types=1);

use PayKit\Bundle\Controller\ApiController;
use PayKit\Bundle\Controller\WebhookController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Import with a prefix, e.g. config/routes/paykit.yaml:
 *
 *     paykit:
 *         resource: '@PayKitBundle/config/routes.php'
 *         prefix: /paykit
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('paykit_webhook', '/webhook')->controller(WebhookController::class)->methods(['POST']);
    $routes->add('paykit_api_subscribe', '/api/subscribe')->controller([ApiController::class, 'subscribe'])->methods(['POST']);
    $routes->add('paykit_api_customer_portal', '/api/customer-portal')->controller([ApiController::class, 'customerPortal'])->methods(['POST']);
    $routes->add('paykit_api_customer', '/api/customer')->controller([ApiController::class, 'customer'])->methods(['GET']);
};
