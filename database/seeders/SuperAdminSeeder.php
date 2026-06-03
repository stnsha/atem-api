<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email    = config('credentials.super_admin.email');
        $password = config('credentials.super_admin.password');

        if (empty($email) || empty($password)) {
            throw new RuntimeException(
                'SUPER_ADMIN_EMAIL or SUPER_ADMIN_PASSWORD is not set in .env'
            );
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => 'Super Admin',
                'password' => bcrypt($password),
            ]
        );
    }
}
