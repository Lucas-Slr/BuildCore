<?php

declare(strict_types=1);

namespace App\Cart;

use App\Catalog\CatalogService;
use App\Customer\Customer;
use App\Shared\DomainError;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class CartService
{
    public function __construct(private CatalogService $catalog, private Security $security, private RequestStack $requests, private EntityManagerInterface $em)
    {
    }
    /** @return array<string, int> */
    public function quantities(): array
    {
        $u = $this->security->getUser();
        return $u instanceof Customer ? $u->cart : $this->requests->getSession()->get('cart', []);
    }
    /** @param array<string, int> $items */
    public function store(array $items): void
    {
        $u = $this->security->getUser();
        if ($u instanceof Customer) {
            $u->cart = $items;
            $this->em->flush();
        } else {
            $this->requests->getSession()->set('cart', $items);
        }
    }
    /** @return list<array{id:string,name:string,components:list<string>}> */
    public function groups(): array
    {
        $u = $this->security->getUser();
        return $u instanceof Customer ? $u->cartGroups : $this->requests->getSession()->get('cartGroups', []);
    }
    /** @param list<array{id:string,name:string,components:list<string>}> $groups */
    private function storeGroups(array $groups): void
    {
        $u = $this->security->getUser();
        if ($u instanceof Customer) {
            $u->cartGroups = $groups;
        } else {
            $this->requests->getSession()->set('cartGroups', $groups);
        }
    }
    /** @param list<string> $ids */
    public function configuration(array $ids, string $name, ?string $groupId = null): void
    {
        $items = $this->quantities();
        $groups = $this->groups();
        if ($groupId !== null) {
            $found = false;
            foreach ($groups as $index => $group) {
                if ($group['id'] !== $groupId) {
                    continue;
                }
                $found = true;
                foreach ($group['components'] as $id) {
                    $items[$id] = max(0, ($items[$id] ?? 0) - 1);
                    if ($items[$id] === 0) {
                        unset($items[$id]);
                    }
                }
                unset($groups[$index]);
            }
            if (!$found) {
                throw new DomainError('GROUP_NOT_FOUND', 'Configuration du panier introuvable.', 404);
            }
        }
        foreach ($ids as $id) {
            $quantity = ($items[$id] ?? 0) + 1;
            if ($quantity > 10 || $quantity > $this->catalog->variant($id)->available()) {
                throw new DomainError('INSUFFICIENT_STOCK', 'Stock insuffisant pour cette configuration.', 409);
            }
            $items[$id] = $quantity;
        }
        if (count($items) > 50 || count($groups) >= 20) {
            throw new DomainError('CART_LIMIT', 'Le panier est limité à 50 références et 20 configurations.');
        }
        if ($ids) {
            $groups[] = ['id' => $groupId ?? bin2hex(random_bytes(8)), 'name' => mb_substr(trim($name) ?: 'Ma configuration', 0, 80), 'components' => $ids];
        }
        $this->storeGroups(array_values($groups));
        $this->store($items);
    }
    /** @param array<string, mixed> $changes */
    public function change(array $changes, bool $add = false): void
    {
        $items = $this->quantities();
        foreach ($changes as $id => $qty) {
            if (!is_int($qty) || $qty < 0 || $qty > 10) {
                throw new DomainError('QUANTITY_INVALID', 'La quantité doit être comprise entre 0 et 10.');
            }
            $next = $add ? ($items[$id] ?? 0) + $qty : $qty;
            if ($next === 0) {
                unset($items[$id]);
                continue;
            }
            $v = $this->catalog->variant($id);
            if ($next > 10 || $next > $v->available()) {
                throw new DomainError('INSUFFICIENT_STOCK', 'La quantité demandée dépasse le stock disponible.', 409);
            }
            $items[$id] = $next;
        }
        if (count($items) > 50) {
            throw new DomainError('CART_LIMIT', 'Le panier est limité à 50 références.');
        }
        // Editing a component independently detaches affected groups; other lines remain in the cart.
        $this->storeGroups(array_values(array_filter($this->groups(), fn ($group) => !array_intersect($group['components'], array_keys($changes)))));
        $this->store($items);
    }
    /** @return array<string, mixed> */
    public function view(): array
    {
        $lines = [];
        $subtotal = 0;
        $valid = true;
        foreach ($this->quantities() as $id => $qty) {
            try {
                $v = $this->catalog->variant($id);
                $row = $this->catalog->view($v);
                $row['quantity'] = $qty;
                $row['amount'] = $v->price * $qty;
                $row['valid'] = $qty <= $v->available();
                $subtotal += $row['amount'];
                $valid = $valid && $row['valid'];
                $lines[] = $row;
            } catch (DomainError) {
                $lines[] = ['id' => $id,'name' => 'Produit indisponible','quantity' => $qty,'price' => 0,'amount' => 0,'valid' => false,'images' => []];
                $valid = false;
            }
        }
        $shipping = $subtotal >= 150000 || !$lines ? 0 : 990;
        return ['items' => $lines,'groups' => $this->groups(),'subtotal' => $subtotal,'shipping' => $shipping,'total' => $subtotal + $shipping,'currency' => 'EUR','valid' => $valid && count($lines) > 0];
    }
}
