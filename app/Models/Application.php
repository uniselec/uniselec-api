<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use EloquentFilter\Filterable;

class Application extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'user_id',
        'form_data',
        'process_selection_id',
        'verification_code',
        "name_source",
        "birthdate_source",
        "cpf_source",
    ];

    protected $casts = [
        'form_data' => 'array',
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
