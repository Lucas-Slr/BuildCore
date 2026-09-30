<?php

declare(strict_types=1);

namespace App\Customer;

use App\Shared\DomainError;

final class AddressValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public static function validate(array $data): array
    {
        $result = [];
        foreach (['name','street','city','postalCode','country'] as $key) {
            $value = $data[$key] ?? null;
            if (!is_string($value) || trim($value) === '' || strlen($value) > 180) {
                throw new DomainError('ADDRESS_INVALID', 'Complétez tous les champs de l’adresse.');
            } $result[$key] = trim($value);
        }
        if ($result['country'] !== 'FR' || !preg_match('/^(0[1-9]|[1-8][0-9]|9[0-5])[0-9]{3}$/', $result['postalCode']) || str_starts_with($result['postalCode'], '20')) {
            throw new DomainError('DELIVERY_ZONE', 'La démonstration livre en France métropolitaine continentale.');
        }
        return $result;
    }
}
