<?php


namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProcessSelectionCourse extends Pivot
{
    use HasFactory, Filterable;

    protected $table = 'process_selection_courses';

    protected $fillable = ['process_selection_id', 'course_id', 'vacancies'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function processSelection()
    {
        return $this->belongsTo(ProcessSelection::class);
    }
}

