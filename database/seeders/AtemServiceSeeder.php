<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AtemServiceSeeder extends Seeder
{
    /**
     * Seed a dedicated service account used by the odb frontend JWT proxy.
     * Keeping this separate from the super admin avoids placing the admin
     * password in the odb codebase. The auth model will be specialised later.
     */
    public function run(): void
    {
        $email    = config('credentials.atem_service.email');
        $password = config('credentials.atem_service.password');

        if (empty($email) || empty($password)) {
            // Fall back to local development defaults when env is not configured.
            $email    = 'atem-service@local';
            $password = 'atem-service-local';
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => 'ATEM Service',
                'password' => bcrypt($password),
            ]
        );
    }
}
