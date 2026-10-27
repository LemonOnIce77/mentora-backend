<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * FR-05: Upload Dokumen Kredensial (Tabel mentor_credentials)
     */
    public function up(): void
    {
        Schema::create('mentor_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('mentor_application_id')->nullable()->constrained('mentor_applications')->onDelete('set null');
            $table->enum('jenis_dokumen', ['transkrip', 'sertifikat', 'portofolio']);
            $table->string('judul_dokumen');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size'); // Dalam bytes
            $table->string('mime_type');
            $table->enum('status_verifikasi', ['PENDING', 'VALID', 'INVALID'])->default('PENDING');
            $table->timestamps();

            $table->index(['user_id', 'jenis_dokumen']);
            $table->index('status_verifikasi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mentor_credentials');
    }
};
