<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiwayatPoin extends Model
{
    use HasFactory;

    protected $table = 'riwayat_poin';

    protected $fillable = ['user_id', 'sesi_id', 'jumlah_poin', 'tipe_transaksi', 'keterangan', 'waktu_transaksi'];

    protected function casts(): array
    {
        return ['jumlah_poin' => 'integer', 'waktu_transaksi' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function session()
    {
        return $this->belongsTo(SesiExchange::class, 'sesi_id');
    }
}
