<?php

namespace App\Services\Settings;

use App\Models\Settings\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ProfileService
{
    /**
     * Update user profile data.
     * - Hashes password when provided.
     * - Resets email verification when email changes.
     * - Runs inside a DB transaction.
     *
     * @param User $user
     * @param array $data
     * @return User
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            if (array_key_exists('password', $data)) {
                if (!empty($data['password'])) {
                    $data['password'] = Hash::make($data['password']);
                } else {
                    unset($data['password']);
                }
            }

            $user->fill($data);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

            return $user->fresh();
        });
    }

    /**
     * Delete user and try to clean related tokens if present.
     * Runs inside a DB transaction.
     *
     * @param User $user
     * @return void
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            // If the application uses personal access tokens (sanctum/passport), try to remove them.
            if (method_exists($user, 'tokens')) {
                try {
                    $user->tokens()->delete();
                } catch (\Throwable $e) {
                    // ignore if tokens relation not available or deletion fails
                }
            }

            $user->delete();
        });
    }
}
