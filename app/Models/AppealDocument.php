<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppealDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'appeal_id',
        'path',
        'original_name',
    ];

    public function appeal()
    {
        return $this->belongsTo(Appeal::class);
    }

    /**
     * Get the full public URL for the stored file.
     */
    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }
}
