<?php

namespace Database\Seeders;

use App\Enums\Permission as P;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (P::cases() as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission->value],
                ['name' => $permission->label(), 'group' => $permission->group()],
            );
        }
    }
}
