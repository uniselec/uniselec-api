<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appeal extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'justification',
        'decision',
        'status',
        'reviewed_by',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function documents()
    {
        return $this->hasMany(AppealDocument::class);
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
