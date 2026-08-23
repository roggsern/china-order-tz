<?php

namespace App\Payments\Gateways\Snippe;

use App\Models\Order;
use App\Models\ShippingAddress;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Resolves real Snippe customer identity from order-time snapshots.
 * Never fabricates placeholder names or emails.
 */
final class SnippeCustomerIdentityResolver
{
    /**
     * @return array{firstname: string, lastname: string, email: string}
     *
     * @throws ValidationException
     */
    public static function resolve(Order $order): array
    {
        $order->loadMissing(['shippingAddress', 'user']);

        if ($order->shippingAddress !== null) {
            return self::fromShippingAddress($order->shippingAddress);
        }

        if ($order->user !== null) {
            return self::fromUser($order->user);
        }

        throw ValidationException::withMessages([
            'customer' => ['Customer identity is required for Snippe mobile money payments.'],
        ]);
    }

    /**
     * @return array{firstname: string, lastname: string, email: string}
     *
     * @throws ValidationException
     */
    private static function fromShippingAddress(ShippingAddress $address): array
    {
        return self::assertComplete(
            self::normalizeNamePart($address->first_name),
            self::normalizeNamePart($address->last_name),
            self::normalizeEmail($address->email),
        );
    }

    /**
     * @return array{firstname: string, lastname: string, email: string}
     *
     * @throws ValidationException
     */
    private static function fromUser(User $user): array
    {
        $firstname = self::normalizeNamePart($user->first_name);
        $lastname = self::normalizeNamePart($user->last_name);

        if ($firstname === '' || $lastname === '') {
            $parsed = self::parseCombinedName((string) $user->name);

            if ($firstname === '') {
                $firstname = $parsed['firstname'];
            }

            if ($lastname === '') {
                $lastname = $parsed['lastname'];
            }
        }

        return self::assertComplete(
            $firstname,
            $lastname,
            self::normalizeEmail($user->email),
        );
    }

    /**
     * @return array{firstname: string, lastname: string}
     */
    private static function parseCombinedName(string $name): array
    {
        $trimmed = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($trimmed === '') {
            return ['firstname' => '', 'lastname' => ''];
        }

        $parts = preg_split('/\s+/u', $trimmed, 2) ?: [];
        $firstname = self::normalizeNamePart($parts[0] ?? '');
        $lastname = self::normalizeNamePart($parts[1] ?? '');

        if ($firstname === '' || $lastname === '') {
            return ['firstname' => '', 'lastname' => ''];
        }

        return [
            'firstname' => $firstname,
            'lastname' => $lastname,
        ];
    }

    private static function normalizeNamePart(?string $value): string
    {
        return trim((string) $value);
    }

    private static function normalizeEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    /**
     * @return array{firstname: string, lastname: string, email: string}
     *
     * @throws ValidationException
     */
    private static function assertComplete(string $firstname, string $lastname, string $email): array
    {
        $errors = [];

        if ($firstname === '') {
            $errors['customer.firstname'] = ['Customer first name is required for Snippe mobile money payments.'];
        }

        if ($lastname === '') {
            $errors['customer.lastname'] = ['Customer last name is required for Snippe mobile money payments.'];
        }

        if ($email === '') {
            $errors['customer.email'] = ['Customer email is required for Snippe mobile money payments.'];
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['customer.email'] = ['Customer email must be a valid email address for Snippe mobile money payments.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
        ];
    }
}
