<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Crear usuario administrador
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin@kardex.com',
            'password' => Hash::make('admin123'),
            'phone' => '1234567890',
            'position' => 'Administrador',
            'is_active' => true,
        ]);

        // Crear un cajero de ejemplo
        $cashier = User::create([
            'name' => 'Cajero Demo',
            'email' => 'cajero@kardex.com',
            'password' => Hash::make('cajero123'),
            'phone' => '0987654321',
            'position' => 'Cajero',
            'is_active' => true,
        ]);

        $this->command->info('✅ Usuarios creados:');
        $this->command->info('Admin - Email: admin@kardex.com, Password: admin123');
        $this->command->info('Cajero - Email: cajero@kardex.com, Password: cajero123');
    }
}
