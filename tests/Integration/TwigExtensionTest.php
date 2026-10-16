<?php

declare(strict_types=1);

namespace PayKit\Bundle\Tests\Integration;

use PayKit\Bundle\Security\CustomerResolverInterface;
use PayKit\Bundle\Security\ResolvedCustomer;
use PayKit\Bundle\Tests\PayKitTestCase;
use PayKit\Bundle\Twig\PayKitExtension;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TwigExtensionTest extends PayKitTestCase
{
    public function testTwigFunctions(): void
    {
        $this->payKit->push();
        $this->payKit->upsertCustomer('user_1');
        $this->payKit->report('user_1', 'messages', 30);

        $resolver = new class implements CustomerResolverInterface {
            public function resolve(?Request $request = null): ?ResolvedCustomer
            {
                return new ResolvedCustomer('user_1');
            }
        };
        $twig = new Environment(new ArrayLoader([
            't' => "{{ paykit_plan('base') }}|{{ paykit_can('pro_models') ? 'yes' : 'no' }}|{{ paykit_balance('messages').remaining }}|{{ paykit_can('pro_models', 'ghost') ? 'yes' : 'no' }}",
        ]));
        $twig->addExtension(new PayKitExtension($this->payKit, $resolver));

        self::assertSame('free|no|70|no', $twig->render('t'));
    }
}
