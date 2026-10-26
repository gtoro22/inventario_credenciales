<?php

namespace Database\Seeders;

use App\Enums\CredentialStatus;
use App\Enums\Environment;
use App\Models\Credential;
use App\Models\CredentialType;
use App\Models\CriticalityLevel;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos de ejemplo. Todos los valores son ficticios y aleatorios.
 * Las contraseñas de los usuarios demo son aleatorias y se muestran una sola vez.
 */
class DemoDataSeeder extends Seeder
{
    /** @var array<string, string> email => contraseña en claro (solo para mostrar al instalar) */
    public array $createdUsers = [];

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $users = [
            ['Ana Gestora', 'gestora@demo.test', 'project-manager'],
            ['Luis Lector', 'lector@demo.test', 'read-only'],
            ['Alicia Auditora', 'auditora@demo.test', 'auditor'],
        ];
        $created = [];
        foreach ($users as [$name, $email, $role]) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                $password = Str::password(16);
                $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'is_active' => true]);
                $user->roles()->sync(Role::where('slug', $role)->pluck('id'));
                $this->createdUsers[$email] = $password;
            }
            $created[$role] = $user;
        }

        $projects = [
            ['Portal de Clientes', 'PORTAL', ['web', 'produccion']],
            ['API de Pagos', 'PAGOS', ['api', 'finanzas']],
            ['Plataforma de Datos', 'DATOS', ['bi', 'cloud']],
        ];

        $types = CredentialType::pluck('id', 'slug');
        $levels = CriticalityLevel::pluck('id', 'slug');
        $super = User::whereHas('roles', fn ($q) => $q->where('slug', Role::SUPER_ADMIN))->first();

        foreach ($projects as $i => [$name, $code, $tags]) {
            $project = Project::firstOrCreate(['code' => $code], ['name' => $name, 'description' => "Proyecto de ejemplo: {$name}.", 'tags' => $tags, 'is_active' => true, 'owner_id' => $created['project-manager']->id]);

            // La gestora ve los dos primeros proyectos; el lector y la auditora solo el primero.
            $project->users()->syncWithoutDetaching(array_filter([
                $i < 2 ? $created['project-manager']->id : null,
                $i === 0 ? $created['read-only']->id : null,
                $i === 0 ? $created['auditor']->id : null,
            ]));

            if ($project->credentials()->exists()) {
                continue;
            }

            $samples = [
                ['Base de datos principal', 'database', 'critical', 'production', 3306, 90],
                ['Servidor de aplicación', 'ssh', 'high', 'production', 22, 25],
                ['API de notificaciones', 'api_key', 'medium', 'staging', null, 12],
                ['Token de CI/CD', 'access_token', 'medium', 'development', null, null],
                ['Cuenta cloud QA', 'cloud_account', 'low', 'qa', null, null],
            ];
            foreach ($samples as [$cname, $type, $level, $env, $port, $days]) {
                Credential::create([
                    'project_id' => $project->id,
                    'credential_type_id' => $types[$type],
                    'criticality_level_id' => $levels[$level],
                    'name' => "{$cname} ({$code})",
                    'username' => 'demo_'.Str::lower(Str::random(5)),
                    'secret' => 'demo-'.Str::random(24),
                    'host' => Str::lower($code).'.example.test',
                    'port' => $port,
                    'environment' => Environment::from($env),
                    'status' => CredentialStatus::Active,
                    'expires_at' => $days ? now()->addDays($days)->toDateString() : null,
                    'notes' => 'Dato de demostración.',
                    'created_by' => $super?->id,
                    'updated_by' => $super?->id,
                ]);
            }
        }
    }
}
