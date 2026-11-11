<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_poin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sesi_id')->nullable()->constrained('sesi_exchanges')->nullOnDelete();
            $table->unsignedInteger('jumlah_poin');
            $table->enum('tipe_transaksi', ['ADD', 'DEDUCT']);
            $table->string('keterangan');
            $table->timestamp('waktu_transaksi')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'waktu_transaksi']);
            $table->index('sesi_id');
        });

        Schema::create('e_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nomor_sertifikat')->unique();
            $table->timestamp('tanggal_terbit')->useCurrent();
            $table->unsignedInteger('poin_ditebus')->default(25);
            $table->string('file_url')->nullable();
            $table->string('status')->default('ISSUED');
            $table->timestamps();

            $table->index(['user_id', 'tanggal_terbit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_certificates');
        Schema::dropIfExists('riwayat_poin');
    }
};
