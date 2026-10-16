<?php

declare(strict_types=1);

namespace PayKit\Bundle\Tests\Integration;

use PayKit\Bundle\Security\CustomerResolverInterface;
use PayKit\Bundle\Security\FeatureVoter;
use PayKit\Bundle\Security\ResolvedCustomer;
use PayKit\Bundle\Tests\PayKitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class FeatureVoterTest extends PayKitTestCase
{
    public function testVotesOnFeatureAccess(): void
    {
        $this->payKit->push();
        $this->payKit->upsertCustomer('user_1');
        $resolver = new class implements CustomerResolverInterface {
            public function resolve(?Request $request = null): ?ResolvedCustomer
            {
                return new ResolvedCustomer('user_1');
            }
        };
        $voter = new FeatureVoter($this->payKit, $resolver);
        $token = new NullToken();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, 'messages', [FeatureVoter::ATTRIBUTE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, 'pro_models', [FeatureVoter::ATTRIBUTE]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, 'pro_models', ['ROLE_USER']));
    }
}
