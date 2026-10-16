<?php

declare(strict_types=1);

namespace PayKit\Bundle\Security;

use PayKit\Bundle\PayKit;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * `$this->denyAccessUnlessGranted('PAYKIT_FEATURE', 'pro_models')` or `{% if is_granted('PAYKIT_FEATURE', 'pro_models') %}`.
 *
 * @extends Voter<string, string>
 */
final class FeatureVoter extends Voter
{
    public const ATTRIBUTE = 'PAYKIT_FEATURE';

    public function __construct(
        private readonly PayKit $payKit,
        private readonly CustomerResolverInterface $resolver,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ATTRIBUTE === $attribute && \is_string($subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $customer = $this->resolver->resolve();

        return null !== $customer && $this->payKit->can($customer->id, $subject);
    }
}
