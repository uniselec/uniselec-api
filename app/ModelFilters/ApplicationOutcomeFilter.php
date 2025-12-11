<?php

namespace App\ModelFilters;

use Illuminate\Database\Eloquent\Builder;

class ApplicationOutcomeFilter extends DefaultModelFilter
{
    protected $sortable = ['created_at'];

    public function search($search)
    {
        $this->where('name', 'LIKE', "%$search%");
    }
    protected $drop_id = false;

    public function processSelectionId($id): void
    {
        $this->whereHas(
            'application',
            fn(Builder $q) =>
            $q->where('process_selection_id', $id)
        );
    }

    /** ?course_id=  — form_data->position->id */
    public function courseId($id): void
    {
        $this->whereHas(
            'application',
            fn(Builder $q) =>
            $q->where('form_data->position->id', (int) $id)
        );
    }

    /** ?admission_category_id=  — dentro do array admission_categories */
    public function admissionCategoryId($id): void
    {
        $this->whereHas('application', function (Builder $q) use ($id) {
            $q->whereJsonContains('form_data->admission_categories', ['id' => (int) $id]);
        });
    }

    public function status($status)
    {
        $this->where('status', 'LIKE', "%$status%");
    }
    /**
     * Related Models that have ModelFilters as well as the method on the ModelFilter
     * As [relationMethod => [input_key1, input_key2]].
     *
     * @var array
     */
    public $relations = [];
}
