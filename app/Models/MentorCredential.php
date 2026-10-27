<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MentorCredential extends Model
{
    use HasFactory;

    protected $table = 'mentor_credentials';

    protected $fillable = [
        'user_id',
        'mentor_application_id',
        'jenis_dokumen',
        'judul_dokumen',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'status_verifikasi',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function application()
    {
        return $this->belongsTo(MentorApplication::class, 'mentor_application_id');
    }
}
