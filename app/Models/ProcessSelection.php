<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'bonus_options',
        'remap_rules'
    ];

    protected $casts = [
        'courses' => 'array',
        'admission_categories' => 'array',
        'knowledge_areas' => 'array',
        'bonus_options' => 'array',
        'allowed_enem_years' => 'array',
        'remap_rules'  => 'array',
    ];

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
    public function convocationLists(): HasMany
    {
        return $this->hasMany(ConvocationList::class);
    }
}
