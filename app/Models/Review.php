<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = ['sesi_id', 'reviewer_id', 'reviewed_user_id', 'rating', 'komentar'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function session()
    {
        return $this->belongsTo(SesiExchange::class, 'sesi_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewedUser()
    {
        return $this->belongsTo(User::class, 'reviewed_user_id');
    }
}
