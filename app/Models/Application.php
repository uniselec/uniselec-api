<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use EloquentFilter\Filterable;

class Application extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'user_id',
        'data',
        'process_selection_id',
        'verification_code'
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function processSelection()
    {
        return $this->belongsTo(ProcessSelection::class);
    }
    public function applicationOutcome()
    {
        return $this->hasOne(ApplicationOutcome::class);
    }
    public function enemScore()
    {
        return $this->hasOne(EnemScore::class, 'application_id', 'id');
    }
}
