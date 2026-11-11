<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JadwalKetersediaan extends Model
{
    use HasFactory;

    protected $table = 'jadwal_ketersediaan';

    protected $fillable = ['user_id', 'hari', 'jam_mulai', 'jam_selesai', 'is_available'];

    protected function casts(): array
    {
        return ['is_available' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function session()
    {
        return $this->hasOne(SesiExchange::class, 'jadwal_id');
    }
}
