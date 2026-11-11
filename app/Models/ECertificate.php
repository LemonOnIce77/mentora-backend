<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ECertificate extends Model
{
    use HasFactory;

    protected $table = 'e_certificates';

    protected $fillable = ['user_id', 'nomor_sertifikat', 'tanggal_terbit', 'poin_ditebus', 'file_url', 'status'];

    protected function casts(): array
    {
        return ['tanggal_terbit' => 'datetime', 'poin_ditebus' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
