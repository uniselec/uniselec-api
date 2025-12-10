<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use EloquentFilter\Filterable;

class ConvocationList extends Model
{
    use Filterable;
    protected $fillable = [
        'process_selection_id',
        'name',
        'status',
        'convocation_status',
        'result_status',
        'response_status',
        'category_ranking',
        'general_ranking',
        'published_at',
        'generated_by'
    ];

    protected $casts = [
        'published_at' => 'datetime'
    ];


    public function processSelection(): BelongsTo
    {
        return $this->belongsTo(ProcessSelection::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(ConvocationListSeat::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(ConvocationListApplication::class);
    }

    public function generatorAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'generated_by');
    }
}
