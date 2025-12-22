<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'remap_rules',
        'last_applications_processed_at',
        'preliminary_result_date',
        'appeal_start_date',
        'appeal_end_date',
        'final_result_date',
    ];

    protected $casts = [
        'courses'             => 'array',
        'admission_categories' => 'array',
        'knowledge_areas' => 'array',
        'bonus_options' => 'array',
        'allowed_enem_years' => 'array',
        'remap_rules'  => 'array',
        'last_applications_processed_at' => 'datetime',
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

    protected function lastApplicationsProcessedAt(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value
                ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i')
                : null
        );
    }
}
