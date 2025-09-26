<?php

namespace App\ModelFilters;

class ConvocationListSeatFilter extends DefaultModelFilter
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
        $this->where('current_admission_category_id', $id);
    }
    /**
     * Related Models that have ModelFilters as well as the method on the ModelFilter
     * As [relationMethod => [input_key1, input_key2]].
     *
     * @var array
     */
    public $relations = [];
}
