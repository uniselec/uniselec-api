<?php

namespace App\ModelFilters;

class AdminFilter extends DefaultModelFilter
{
    protected $sortable = ['created_at'];

    public function search($search)
    {
        $this->where(function ($query) use ($search) {
            $query->where('name', 'LIKE', "%$search%")
                  ->orWhere('email', 'LIKE', "%$search%");
        });
    }

    /**
     * Related Models that have ModelFilters as well as the method on the ModelFilter
     * As [relationMethod => [input_key1, input_key2]].
     *
     * @var array
     */
    public $relations = [];
}
