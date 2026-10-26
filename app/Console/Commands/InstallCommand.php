<?php

namespace App\Console\Commands;

use App\Models\CredentialType;
use App\Models\CriticalityLevel;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'credentials:install
        {--with-demo-data : Crea proyectos, credenciales y usuarios de ejemplo (valores ficticios)}
        {--name= : Nombre del superadministrador}
        {--email= : Correo del superadministrador}
        {--password= : Contraseña (si se omite se pide o se genera una aleatoria)}';

    protected $description = 'Inicializa el sistema: verifica MySQL, migra, siembra roles/permisos/perfiles y crea el superadministrador.';

    public function handle(): int
    {
        if (empty(config('app.key'))) {
            $this->error('Falta APP_KEY. Ejecuta primero: php artisan key:generate');

            return self::FAILURE;
        }

        $this->components->info('1/5 Verificando conexión a la base de datos...');
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->error('No se pudo conectar a la base de datos: '.$e->getMessage());
            $this->line('Revisa DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME y DB_PASSWORD en tu archivo .env.');

            return self::FAILURE;
        }
        $this->line('   Conectado a '.DB::connection()->getDriverName().' / '.DB::connection()->getDatabaseName());

        $this->components->info('2/5 Ejecutando migraciones...');
        $this->call('migrate', ['--force' => true]);

        $this->components->info('3/5 Creando catálogos, permisos, roles y perfiles...');
        $this->callSilent('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $this->components->info('4/5 Superadministrador inicial...');
        [$admin, $generatedPassword] = $this->ensureSuperAdmin();

        $demo = null;
        if ($this->option('with-demo-data')) {
            $this->components->info('5/5 Creando datos de ejemplo...');
            $demo = new DemoDataSeeder;
            $demo->setContainer(app())->setCommand($this);
            $demo->run();
        } else {
            $this->components->info('5/5 Datos de ejemplo omitidos (usa --with-demo-data para crearlos).');
        }

        $this->summary($admin, $generatedPassword, $demo);

        return self::SUCCESS;
    }

    /** @return array{0: User|null, 1: string|null} */
    private function ensureSuperAdmin(): array
    {
        $role = Role::where('slug', Role::SUPER_ADMIN)->firstOrFail();
        $existing = User::whereHas('roles', fn ($q) => $q->whereKey($role->id))->where('is_active', true)->first();

        $email = $this->option('email') ?: env('INSTALL_ADMIN_EMAIL');

        if ($existing && ! $email) {
            $this->line("   Ya existe un superadministrador activo ({$existing->email}); no se crea otro.");

            return [null, null];
        }

        $name = $this->option('name') ?: env('INSTALL_ADMIN_NAME');
        $password = $this->option('password') ?: env('INSTALL_ADMIN_PASSWORD');

        if (! $email && $this->input->isInteractive()) {
            $email = text('Correo del superadministrador', required: true);
        }
        if (! $name && $this->input->isInteractive()) {
            $name = text('Nombre del superadministrador', default: 'Super Administrador', required: true);
        }

        $email = Str::lower((string) $email);
        $name = $name ?: 'Super Administrador';

        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            $this->error('Debes indicar un correo válido con --email (o INSTALL_ADMIN_EMAIL).');
            exit(self::FAILURE);
        }

        $generated = null;
        if (! $password) {
            if ($this->input->isInteractive()) {
                $password = password('Contraseña (vacío = generar una aleatoria)') ?: null;
            }
            if (! $password) {
                $password = $generated = Str::password(20);
            }
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $this->line("   El usuario {$email} ya existe: se le asegura el rol Super Administrador (contraseña sin cambios).");
            $user->forceFill(['is_active' => true])->save();
            $generated = null;
        } else {
            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'is_active' => true]);
        }
        $user->roles()->syncWithoutDetaching([$role->id]);

        return [$user, $generated];
    }

    private function summary(?User $admin, ?string $generatedPassword, ?DemoDataSeeder $demo): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=green;options=bold>Instalación completada</>', '');
        $this->table(['Elemento', 'Total'], [
            ['Permisos', Permission::count()],
            ['Roles', Role::count()],
            ['Perfiles', Profile::count()],
            ['Tipos de credencial', CredentialType::count()],
            ['Niveles de criticidad', CriticalityLevel::count()],
            ['Usuarios', User::count()],
        ]);

        if ($admin) {
            $this->line("Superadministrador: <info>{$admin->email}</info>");
        }
        if ($generatedPassword) {
            $this->warn("Contraseña generada (se muestra UNA sola vez, guárdala y cámbiala): {$generatedPassword}");
        }
        if ($demo && $demo->createdUsers) {
            $this->warn('Usuarios demo creados (contraseñas aleatorias, solo se muestran ahora):');
            $this->table(['Correo', 'Contraseña'], collect($demo->createdUsers)->map(fn ($p, $e) => [$e, $p])->values()->all());
        }
        $this->line('Siguiente paso: <info>php artisan serve</info> y <info>npm run dev</info> (o <info>npm run build</info>).');
    }
}
