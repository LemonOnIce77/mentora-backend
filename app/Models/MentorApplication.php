<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MentorApplication extends Model
{
    use HasFactory;

    protected $table = 'mentor_applications';

    protected $fillable = [
        'user_id',
        'alasan_mengajar',
        'bidang_keahlian',
        'tahun_pengalaman',
        'tautan_portofolio',
        'status',
        'catatan_admin',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'tahun_pengalaman' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function credentials()
    {
        return $this->hasMany(MentorCredential::class);
    }
}
