<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use EloquentFilter\Filterable;


class ConvocationListApplication extends Model
{
    use Filterable;
    protected $fillable = [
        'convocation_list_id',
        'application_id',
        'course_id',
        'admission_category_id',
        'seat_id',
        'ranking_at_generation',
        'ranking_in_category',
        'status',
    ];


    public function list(): BelongsTo
    {
        return $this->belongsTo(ConvocationList::class, 'convocation_list_id');
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(ConvocationListSeat::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AdmissionCategory::class, 'admission_category_id');
    }
}
