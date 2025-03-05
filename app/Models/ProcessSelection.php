<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessSelection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'status', 'start_date', 'end_date', 'type'
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
