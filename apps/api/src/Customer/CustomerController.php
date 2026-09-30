<?php

declare(strict_types=1);

namespace App\Customer;

use App\Shared\DomainError;
use App\Compatibility\ConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class CustomerController extends AbstractController
{
    #[Route('/api/v1/auth/csrf', methods: ['GET'])]
    public function csrf(CsrfTokenManagerInterface $csrf): JsonResponse
    {
        return $this->json(['token' => $csrf->getToken('api')->getValue()]);
    }
    #[Route('/api/v1/auth/login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        return $this->json(['authenticated' => true]);
    }
    #[Route('/api/v1/auth/logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Firewall logout');
    }
    #[Route('/api/v1/auth/register', methods: ['POST'])]
    public function register(Request $r, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, ValidatorInterface $validator): JsonResponse
    {
        $d = $r->toArray();
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        $password = (string) ($d['password'] ?? '');
        $name = trim((string) ($d['name'] ?? ''));
        if (count($validator->validate($email, [new Assert\NotBlank(),new Assert\Email(),new Assert\Length(max:180)])) || strlen($password) < 12 || strlen($password) > 128 || strlen($name) < 2 || strlen($name) > 120) {
            throw new DomainError('VALIDATION_FAILED', 'Renseignez un nom, un email valide et un mot de passe de 12 à 128 caractères.');
        }
        $u = new Customer();
        $u->email = $email;
        $u->name = $name;
        $u->password = $hasher->hashPassword($u, $password);
        if (!$em->getRepository(Customer::class)->findOneBy(['email' => $email])) {
            $em->persist($u);
            $em->flush();
        }
        return $this->json(['message' => 'Si ces informations permettent une inscription, votre compte est disponible. Vous pouvez vous connecter.'], 202);
    }
    #[Route('/api/v1/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $u = $this->customer();
        return $this->json(['id' => $u->id,'email' => $u->email,'name' => $u->name,'roles' => $u->getRoles(),'addresses' => $u->addresses,'builds' => $u->builds]);
    }
    #[Route('/api/v1/me/profile', methods: ['PATCH'])]
    public function profile(Request $r, EntityManagerInterface $em): JsonResponse
    {
        $u = $this->customer();
        $name = trim((string) ($r->toArray()['name'] ?? ''));
        if (strlen($name) < 2 || strlen($name) > 120) {
            throw new DomainError('NAME_INVALID', 'Le nom doit contenir entre 2 et 120 caractères.');
        } $u->name = $name;
        $em->flush();
        return $this->me();
    }
    #[Route('/api/v1/me/password', methods: ['POST'])]
    public function password(Request $r, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $u = $this->customer();
        $d = $r->toArray();
        $new = (string) ($d['password'] ?? '');
        if (!$hasher->isPasswordValid($u, (string) ($d['currentPassword'] ?? '')) || strlen($new) < 12 || strlen($new) > 128) {
            throw new DomainError('PASSWORD_INVALID', 'Vérifiez le mot de passe actuel et utilisez au moins 12 caractères.');
        }
        $u->password = $hasher->hashPassword($u, $new);
        $em->flush();
        $r->getSession()->invalidate();
        return $this->json(['message' => 'Mot de passe modifié. Reconnectez-vous.']);
    }
    #[Route('/api/v1/me/addresses', methods: ['POST'])]
    public function address(Request $r, EntityManagerInterface $em): JsonResponse
    {
        $u = $this->customer();
        if (count($u->addresses) >= 10) {
            throw new DomainError('ADDRESS_LIMIT', 'Dix adresses maximum.');
        } $address = AddressValidator::validate($r->toArray());
        $address['id'] = bin2hex(random_bytes(8));
        $u->addresses[] = $address;
        $em->flush();
        return $this->me();
    }
    #[Route('/api/v1/me/addresses/{id}', methods: ['PATCH'])]
    public function editAddress(string $id, Request $r, EntityManagerInterface $em): JsonResponse
    {
        $u = $this->customer();
        foreach ($u->addresses as $index => $address) {
            if ($address['id'] === $id) {
                $u->addresses[$index] = AddressValidator::validate($r->toArray()) + ['id' => $id];
                $em->flush();
                return $this->me();
            }
        }
        throw new DomainError('ADDRESS_NOT_FOUND', 'Adresse introuvable.', 404);
    }
    #[Route('/api/v1/me/addresses/{id}', methods: ['DELETE'])]
    public function deleteAddress(string $id, EntityManagerInterface $em): JsonResponse
    {
        $u = $this->customer();
        $u->addresses = array_values(array_filter($u->addresses, fn ($a) => $a['id'] !== $id));
        $em->flush();
        return $this->me();
    }
    #[Route('/api/v1/me/configurations', methods: ['POST'])]
    public function saveBuild(Request $r, EntityManagerInterface $em, ConfigurationService $service): JsonResponse
    {
        $u = $this->customer();
        $d = $r->toArray();
        $service->check($d['components'] ?? [], 0, true);
        if (count($u->builds) >= 20) {
            throw new DomainError('BUILD_LIMIT', 'Vingt configurations maximum.');
        }
        $u->builds[] = ['id' => bin2hex(random_bytes(8)),'name' => mb_substr((string) ($d['name'] ?? 'Ma configuration'), 0, 80),'components' => $d['components']];
        $em->flush();
        return $this->me();
    }
    #[Route('/api/v1/me/configurations/{id}', methods: ['DELETE'])]
    public function deleteBuild(string $id, EntityManagerInterface $em): JsonResponse
    {
        $u = $this->customer();
        $u->builds = array_values(array_filter($u->builds, fn ($build) => $build['id'] !== $id));
        $em->flush();
        return $this->me();
    }
    private function customer(): Customer
    {
        $u = $this->getUser();
        if (!$u instanceof Customer) {
            throw new DomainError('AUTH_REQUIRED', 'Connectez-vous pour continuer.', 401);
        } return $u;
    }
}
