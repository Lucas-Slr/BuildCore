<?php

declare(strict_types=1);

namespace App\Customer;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'customer')]
class Customer implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\Column(length: 36)] public string $id;
    #[ORM\Column(length: 180, unique: true)] public string $email = '';
    #[ORM\Column(length: 120)] public string $name = '';
    #[ORM\Column(length: 255)] public string $password = '';
    /** @var list<string> */
    #[ORM\Column(type: 'json')] public array $roles = ['ROLE_CUSTOMER'];
    /** @var list<array<string, string>> */
    #[ORM\Column(type: 'json')] public array $addresses = [];
    /** @var array<string, int> */
    #[ORM\Column(type: 'json')] public array $cart = [];
    /** @var list<array{id:string,name:string,components:list<string>}> */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])] public array $cartGroups = [];
    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')] public array $builds = [];
    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
    }
    public function getUserIdentifier(): string
    {
        return $this->email;
    }
    public function getPassword(): string
    {
        return $this->password;
    }
    public function getRoles(): array
    {
        return array_unique([...$this->roles, 'ROLE_CUSTOMER']);
    }
    public function eraseCredentials(): void
    {
    }
}
