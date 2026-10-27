<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * FR-03: Pengelolaan Skill (Tabel Master Skill & Relasi User_Skills)
     */
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('nama_skill')->unique();
            $table->string('kategori');
            $table->text('deskripsi')->nullable();
            $table->string('icon')->nullable();
            $table->timestamps();

            $table->index('kategori');
        });

        Schema::create('user_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('skill_id')->constrained('skills')->onDelete('cascade');
            $table->enum('type', ['teach', 'learn']); // 'teach' = dimiliki untuk diajarkan, 'learn' = ingin dipelajari
            $table->enum('level', ['pemula', 'menengah', 'mahir'])->default('pemula');
            $table->text('deskripsi_pengalaman')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'skill_id', 'type'], 'user_skill_type_unique');
            $table->index(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_skills');
        Schema::dropIfExists('skills');
    }
};
