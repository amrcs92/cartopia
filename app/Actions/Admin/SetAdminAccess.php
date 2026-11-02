<?php

namespace App\Actions\Admin;

use App\Exceptions\AdminActionRefused;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Grants or removes admin access. Granting to an admin, or removing from a
 * non-admin, changes nothing.
 */
class SetAdminAccess
{
    /**
     * @throws AdminActionRefused
     */
    public function handle(User $target, User $admin, bool $isAdmin): User
    {
        if (! $isAdmin && $target->is($admin)) {
            throw new AdminActionRefused("You can't remove your own admin access.");
        }

        return DB::transaction(function () use ($target, $admin, $isAdmin): User {
            // Locking every admin row means two simultaneous removals can't both see another admin left.
            $adminIds = User::query()->where('is_admin', true)->lockForUpdate()->pluck('id');

            $lockedTarget = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if (! $isAdmin && $lockedTarget->is_admin && $adminIds->count() <= 1) {
                throw new AdminActionRefused('Cartopia needs at least one admin.');
            }

            if (! $adminIds->contains($admin->getKey())) {
                throw new AdminActionRefused('Only admins can change admin access.');
            }

            if ($lockedTarget->is_admin !== $isAdmin) {
                $lockedTarget->is_admin = $isAdmin;
                $lockedTarget->save();
            }

            $target->is_admin = $isAdmin;
            $target->syncOriginalAttribute('is_admin');

            return $lockedTarget;
        });
    }
}
