<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $table = 'chat_messages';

    protected $fillable = ['sesi_id', 'pengirim_id', 'penerima_id', 'pesan', 'waktu_kirim', 'is_read'];

    protected function casts(): array
    {
        return ['waktu_kirim' => 'datetime', 'is_read' => 'boolean'];
    }

    public function session()
    {
        return $this->belongsTo(SesiExchange::class, 'sesi_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'pengirim_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'penerima_id');
    }
}
