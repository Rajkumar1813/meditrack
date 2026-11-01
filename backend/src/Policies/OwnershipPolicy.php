<?php

declare(strict_types=1);

namespace MediTrack\Policies;

use MediTrack\Enums\Role;

class OwnershipPolicy
{
    /**
     * Check if the authenticated user can access or modify a user's data.
     */
    public static function canAccessUser(array $currentUser, int $targetUserId): bool
    {
        if ((int)$currentUser['id'] === $targetUserId) {
            return true;
        }

        return in_array($currentUser['role'], [Role::ADMIN->value, Role::PHARMACIST->value], true);
    }

    /**
     * Check if the authenticated user can access or modify an order.
     */
    public static function canAccessOrder(array $currentUser, array $order): bool
    {
        if ((int)$order['user_id'] === (int)$currentUser['id']) {
            return true;
        }

        return in_array($currentUser['role'], [Role::ADMIN->value, Role::PHARMACIST->value, Role::STAFF->value], true);
    }

    /**
     * Check if the authenticated user can access a prescription.
     */
    public static function canAccessPrescription(array $currentUser, array $prescription): bool
    {
        if ((int)$prescription['user_id'] === (int)$currentUser['id']) {
            return true;
        }

        return in_array($currentUser['role'], [Role::ADMIN->value, Role::PHARMACIST->value], true);
    }
}

