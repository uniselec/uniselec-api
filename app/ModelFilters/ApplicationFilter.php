<?php

namespace App\ModelFilters;

class ApplicationFilter extends DefaultModelFilter
{
    protected $sortable = ['created_at'];
    protected $drop_id = false;
    public function search($description)
    {
        $this->where('description', 'LIKE', "%$description%");
    }
    public function processSelectionId($id)
    {
        $this->where('process_selection_id', $id);
    }

    /**
     * Related Models that have ModelFilters as well as the method on the ModelFilter
     * As [relationMethod => [input_key1, input_key2]].
     *
     * @var array
     */
    public $relations = [];
}
