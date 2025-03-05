<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessSelection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'status', 'start_date', 'end_date', 'selection_type'
    ];

    protected $casts = [
        'status' => 'string',
        'selection_type' => 'string',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'process_selection_courses')
            ->withPivot('vacancies');
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
