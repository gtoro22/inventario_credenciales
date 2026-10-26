<?php

namespace Database\Factories;

use App\Enums\CredentialStatus;
use App\Enums\Environment;
use App\Models\Credential;
use App\Models\CredentialType;
use App\Models\CriticalityLevel;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Requiere los catálogos sembrados (CatalogSeeder).
 *
 * @extends Factory<Credential>
 */
class CredentialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'credential_type_id' => fn () => CredentialType::inRandomOrder()->value('id'),
            'criticality_level_id' => fn () => CriticalityLevel::whereIn('slug', ['low', 'medium', 'high'])->inRandomOrder()->value('id'),
            'name' => fake()->words(3, true),
            'username' => fake()->userName(),
            // Valor aleatorio de mentira: jamás un secreto real.
            'secret' => 'fake-'.Str::random(24),
            'host' => fake()->domainName(),
            'port' => fake()->randomElement([22, 443, 3306, 5432, 6379]),
            'environment' => fake()->randomElement(Environment::cases()),
            'notes' => null,
            'expires_at' => null,
            'status' => CredentialStatus::Active,
        ];
    }

    public function level(string $slug): static
    {
        return $this->state(fn () => ['criticality_level_id' => CriticalityLevel::where('slug', $slug)->value('id')]);
    }

    public function critical(): static
    {
        return $this->level('critical');
    }

    public function expiringIn(int $days): static
    {
        return $this->state(['expires_at' => now()->addDays($days)->toDateString()]);
    }
}
