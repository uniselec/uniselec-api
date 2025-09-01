<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessSelection extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'name',
        'description',
        'start_date',
        'end_date',
        'type',
        'courses',
        'admission_categories',
        'knowledge_areas',
        'allowed_enem_years',
        'currenty_step',
        'bonus_options'
    ];

    protected $casts = [
        'courses' => 'array',
        'admission_categories'=> 'array',
        'knowledge_areas'=> 'array',
        'bonus_options'=> 'array',
        'allowed_enem_years'=> 'array',
    ];

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
