<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('criticality_levels', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedTinyInteger('weight')->unique();
            $table->string('color', 20);
            $table->timestamps();
        });

        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credential_type_id')->constrained();
            $table->foreignId('criticality_level_id')->constrained('criticality_levels');
            $table->string('name');
            $table->string('username')->nullable();
            // Contenido cifrado (AES-256-GCM) por App\Services\SecretVault.
            $table->longText('secret')->nullable();
            $table->string('host', 500)->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('environment', 20)->index();
            $table->text('notes')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
        Schema::dropIfExists('criticality_levels');
        Schema::dropIfExists('credential_types');
    }
};
