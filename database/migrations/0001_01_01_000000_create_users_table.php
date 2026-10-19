<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * SCRUM-89 [DB] Membuat migrasi tabel Users (Mentora Specification)
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap');
            $table->string('nama_panggilan')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('universitas')->nullable();
            $table->string('prodi')->nullable();
            $table->string('semester')->nullable();
            $table->string('domisili')->nullable();
            $table->string('tgl_lahir')->nullable();
            $table->text('bio')->nullable();
            $table->string('foto_profil')->nullable();
            $table->string('mode_sesi')->default('Fleksibel');
            $table->string('role')->default('Mentee'); // Mentee, Mentor, Admin
            $table->string('status_mentor')->default('NONE'); // NONE, PENDING, APPROVED, REJECTED
            $table->string('dokumen_kredensial')->nullable();
            $table->integer('total_poin')->default(0);
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->boolean('onboarding_completed')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
