<?php

declare(strict_types=1);

namespace App\Order;

use App\Customer\Customer;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/** @extends Voter<string, Purchase> */
final class OrderVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === 'ORDER_VIEW' && $subject instanceof Purchase;
    }
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $u = $token->getUser();
        return $u instanceof Customer && ($subject->customer->id === $u->id || in_array('ROLE_ADMIN', $u->getRoles(), true));
    }
}
