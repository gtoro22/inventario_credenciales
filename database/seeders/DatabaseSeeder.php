<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Siembra catálogos, permisos, roles y perfiles (idempotente).
 * NO crea usuarios ni contraseñas: usa `php artisan credentials:install`.
 * Datos de ejemplo: `php artisan db:seed --class=DemoDataSeeder`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogSeeder::class,
            PermissionSeeder::class,
            RoleProfileSeeder::class,
        ]);
    }
}
