<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnemScore extends Model
{
    use HasFactory;

    protected $table = 'enem_scores';

    protected $fillable = [
        'application_id',
        'enem',
        'scores',
        'original_scores',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
