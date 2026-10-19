<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nama_lengkap',
        'nama_panggilan',
        'email',
        'password',
        'universitas',
        'prodi',
        'semester',
        'domisili',
        'tgl_lahir',
        'bio',
        'foto_profil',
        'mode_sesi',
        'role',
        'status_mentor',
        'dokumen_kredensial',
        'total_poin',
        'latitude',
        'longitude',
        'onboarding_completed',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'onboarding_completed' => 'boolean',
            'total_poin' => 'integer',
        ];
    }
}
