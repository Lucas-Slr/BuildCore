<?php

declare(strict_types=1);

namespace App\Customer;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class LoginAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }
    public function supports(Request $r): bool
    {
        return $r->getPathInfo() === '/api/v1/auth/login' && $r->isMethod('POST');
    }
    public function authenticate(Request $r): Passport
    {
        $data = $r->toArray();
        return new Passport(new UserBadge(mb_strtolower(trim((string) ($data['email'] ?? '')))), new PasswordCredentials((string) ($data['password'] ?? '')));
    }
    public function onAuthenticationSuccess(Request $r, TokenInterface $token, string $firewallName): JsonResponse
    {
        $user = $token->getUser();
        if ($user instanceof Customer) {
            $guestGroups = $r->getSession()->get('cartGroups', []);
            $clamped = [];
            foreach ($r->getSession()->get('cart', []) as $id => $qty) {
                if (($user->cart[$id] ?? 0) + $qty > 10) {
                    $clamped[] = $id;
                }
                $user->cart[$id] = min(10, ($user->cart[$id] ?? 0) + $qty);
            }
            $user->cartGroups = array_slice(array_values(array_filter([...$user->cartGroups, ...$guestGroups], fn ($group) => !array_intersect($group['components'], $clamped))), 0, 20);
            $r->getSession()->remove('cart');
            $r->getSession()->remove('cartGroups');
            $this->em->flush();
        }
        return new JsonResponse(['authenticated' => true]);
    }
    public function onAuthenticationFailure(Request $r, AuthenticationException $exception): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => 'LOGIN_FAILED','message' => 'Connexion impossible. Vérifiez vos identifiants ou réessayez plus tard.']], 401);
    }
    public function start(Request $r, ?AuthenticationException $authException = null): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => 'AUTH_REQUIRED','message' => 'Connectez-vous pour continuer.']], 401);
    }
}
