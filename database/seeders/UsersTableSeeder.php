<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Super User', 'email' => 'admin@admin.com', 'password' => Hash::make('admin')]
        ];
        collect($users)->each(fn ($user) => User::firstOrCreate(['email' => $user['email']], $user));
    }
}
