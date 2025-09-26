<?php

namespace App\ModelFilters;
use Illuminate\Database\Eloquent\Builder;

class ConvocationListApplicationFilter extends DefaultModelFilter
{
    protected $sortable = ['created_at'];

    public function search($search)
    {
        $this->where('name', 'LIKE', "%$search%");
    }
    public function course($id): void
    {
        $this->where('course_id', $id);
    }
    public function admissionCategory($id): void
    {
        $this->where('admission_category_id', $id);
    }

    public $relations = [];
}
