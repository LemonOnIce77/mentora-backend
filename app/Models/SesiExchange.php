<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SesiExchange extends Model
{
    use HasFactory;

    protected $table = 'sesi_exchanges';

    protected $fillable = [
        'mentor_id', 'mentee_id', 'skill_id', 'jadwal_id', 'tanggal_sesi',
        'jam_mulai', 'jam_selesai', 'metode', 'lokasi', 'status',
    ];

    protected function casts(): array
    {
        return ['tanggal_sesi' => 'datetime'];
    }

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function mentee()
    {
        return $this->belongsTo(User::class, 'mentee_id');
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }

    public function schedule()
    {
        return $this->belongsTo(JadwalKetersediaan::class, 'jadwal_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'sesi_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'sesi_id');
    }

    public function pointHistory()
    {
        return $this->hasMany(RiwayatPoin::class, 'sesi_id');
    }
}
