<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use EloquentFilter\Filterable;
class ApplicationOutcome extends Model
{
    use HasFactory, Filterable;

    protected $table = 'application_outcomes';

    protected $fillable = [
        'application_id',
        'status',
        'classification_status',
        'convocation_status',
        'average_score',
        'final_score',
        'ranking',
        'reason',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}