<?php

namespace App\ModelFilters;

class DocumentFilter extends DefaultModelFilter
{
    protected $sortable = ['created_at'];

    public function search($search)
    {
        $this->where('name', 'LIKE', "%$search%");
    }

    /**
     * Related Models that have ModelFilters as well as the method on the ModelFilter
     * As [relationMethod => [input_key1, input_key2]].
     *
     * @var array
     */
    public $relations = [];
}
