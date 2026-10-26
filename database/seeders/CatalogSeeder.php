<?php

namespace Database\Seeders;

use App\Models\CredentialType;
use App\Models\CriticalityLevel;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'user_password' => 'Usuario y contraseña',
            'api_key' => 'API key / API secret',
            'access_token' => 'Token de acceso',
            'database' => 'Conexión a base de datos',
            'ssh' => 'Acceso a servidor SSH',
            'cloud_account' => 'Cuenta de cloud provider',
            'certificate' => 'Certificado o archivo sensible',
            'other' => 'Otro',
        ];
        foreach ($types as $slug => $name) {
            CredentialType::updateOrCreate(['slug' => $slug], ['name' => $name]);
        }

        $levels = [
            ['low', 'Baja', 1, 'green'],
            ['medium', 'Media', 2, 'gold'],
            ['high', 'Alta', 3, 'orange'],
            ['critical', 'Crítica', 4, 'red'],
        ];
        foreach ($levels as [$slug, $name, $weight, $color]) {
            CriticalityLevel::updateOrCreate(['slug' => $slug], ['name' => $name, 'weight' => $weight, 'color' => $color]);
        }
    }
}
