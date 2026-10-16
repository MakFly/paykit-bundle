<?php

declare(strict_types=1);

namespace App\Tests\Module;

use App\Tests\ApiTestCase;
use PayKit\Bundle\PayKit;

final class SaasApiTest extends ApiTestCase
{
    public function testSubscriptionLifecyclePersistsAcrossHttpRequests(): void
    {
        [, $initial] = $this->call('/api/saas/bootstrap', []);
        self::assertCount(1, $initial['customer']['subscriptions']);
        self::assertSame('free', $initial['customer']['subscriptions'][0]['planId']);
        self::assertSame(['free', 'pro', 'ultra'], array_column($initial['plans'], 'id'));
        self::assertSame(['messages'], array_keys($initial['customer']['entitlements']));

        [, $pro] = $this->call('/api/saas/subscribe', ['planId' => 'pro']);
        self::assertSame('activated', $pro['result']['action']);
        self::assertTrue($pro['proAllowed']);
        self::assertSame(100, $pro['customer']['entitlements']['messages']['limit']);

        [, $ultra] = $this->call('/api/saas/subscribe', ['planId' => 'ultra']);
        self::assertSame('upgraded', $ultra['result']['action']);
        self::assertTrue($ultra['customer']['entitlements']['messages']['unlimited']);

        [, $scheduled] = $this->call('/api/saas/subscribe', ['planId' => 'free']);
        self::assertSame('scheduled', $scheduled['result']['action']);
        self::assertSame('free', $scheduled['customer']['subscriptions'][0]['scheduledPlanId']);

        [, $resumed] = $this->call('/api/saas/subscribe', ['planId' => 'ultra']);
        self::assertSame('resumed', $resumed['result']['action']);

        [, $persisted] = $this->call('/api/saas');
        self::assertSame('ultra', $persisted['customer']['subscriptions'][0]['planId']);
    }

    public function testChatConsumesMessagesAndGatesTheExpertModel(): void
    {
        $this->call('/api/saas/bootstrap', []);
        [$status, $denied] = $this->call('/api/saas/chat', ['prompt' => 'en fait le texte', 'model' => 'expert']);
        self::assertSame(402, $status);
        self::assertSame('model', $denied['result']['reason']);
        self::assertSame(10, $denied['customer']['entitlements']['messages']['remaining'], 'A refused model consumes nothing.');

        [$status, $reply] = $this->call('/api/saas/chat', ['prompt' => 'en fait le texte est prêt']);
        self::assertSame(200, $status);
        self::assertSame('standard', $reply['result']['reply']['model']);
        self::assertStringContainsString('Le texte est prêt.', $reply['result']['reply']['text']);
        self::assertSame(9, $reply['customer']['entitlements']['messages']['remaining']);

        for ($i = 0; $i < 9; ++$i) {
            $this->call('/api/saas/chat', ['prompt' => 'Encore un essai.']);
        }
        [$status, $exhausted] = $this->call('/api/saas/chat', ['prompt' => 'Un de trop.']);
        self::assertSame(402, $status);
        self::assertSame('quota', $exhausted['result']['reason']);
        self::assertSame(0, $exhausted['customer']['entitlements']['messages']['remaining']);

        $this->call('/api/saas/subscribe', ['planId' => 'pro']);
        [$status, $expert] = $this->call('/api/saas/chat', ['prompt' => 'Le texte.', 'model' => 'expert']);
        self::assertSame(200, $status);
        self::assertSame('expert', $expert['result']['reply']['model']);
    }

    public function testInvalidAndCrossOriginRequestsDoNotMutateUsage(): void
    {
        [$status] = $this->call('/api/saas/bootstrap', [], ['HTTP_ORIGIN' => 'http://127.0.0.1:'.(getenv('WEB_PORT') ?: '5184')]);
        self::assertSame(200, $status);
        [$status] = $this->call('/api/saas/chat', ['prompt' => '']);
        self::assertSame(400, $status);
        [$status] = $this->call('/api/saas/subscribe', ['planId' => 'club']);
        self::assertSame(400, $status, 'A plan of another module is rejected.');
        [$status] = $this->call('/api/saas/chat', ['prompt' => 'Bonjour.', 'model' => 'gpt']);
        self::assertSame(400, $status);
        [$status] = $this->call('/api/saas/chat', ['prompt' => 'Bonjour.'], ['HTTP_ORIGIN' => 'https://other.example']);
        self::assertSame(403, $status);
        [, $state] = $this->call('/api/saas');
        self::assertSame(10, $state['customer']['entitlements']['messages']['remaining']);
    }

    public function testBootstrapRevivesADeletedCustomer(): void
    {
        $this->call('/api/saas/bootstrap', []);
        $this->bootedContainer()->get(PayKit::class)->deleteCustomer('saas-customer');

        [$status, $revived] = $this->call('/api/saas/bootstrap', []);
        self::assertSame(200, $status);
        [, $pro] = $this->call('/api/saas/subscribe', ['planId' => 'pro']);
        self::assertSame('activated', $pro['result']['action'], 'The kept card still activates paid plans.');
    }
}
