<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_ketersediaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('hari', 20);
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'hari', 'is_available'], 'availability_user_day_idx');
        });

        Schema::create('sesi_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jadwal_id')->nullable()->unique()->constrained('jadwal_ketersediaan')->nullOnDelete();
            $table->dateTime('tanggal_sesi');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('metode', 20);
            $table->string('lokasi')->nullable();
            $table->enum('status', ['BOOKED', 'COMPLETED', 'CANCELLED'])->default('BOOKED');
            $table->timestamps();

            $table->index(['mentor_id', 'status']);
            $table->index(['mentee_id', 'status']);
            $table->index('tanggal_sesi');
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesi_id')->nullable()->constrained('sesi_exchanges')->cascadeOnDelete();
            $table->foreignId('pengirim_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('penerima_id')->constrained('users')->cascadeOnDelete();
            $table->text('pesan');
            $table->timestamp('waktu_kirim')->useCurrent();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['sesi_id', 'waktu_kirim']);
            $table->index(['penerima_id', 'is_read']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesi_id')->constrained('sesi_exchanges')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('komentar')->nullable();
            $table->timestamps();

            $table->index(['reviewed_user_id', 'created_at']);
            $table->index(['sesi_id', 'reviewer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('sesi_exchanges');
        Schema::dropIfExists('jadwal_ketersediaan');
    }
};
