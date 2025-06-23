<?php

namespace App\ModelFilters;

class UserFilter extends DefaultModelFilter
{
    protected $sortable = ['created_at'];

    public function search($name)
    {
        $this->where('name', 'LIKE', "%$name%");
    }

    public function name($name)
    {
        $this->where('name', 'LIKE', "%$name%");
    }
    // public function status($status)
    // {
    //     $this->where('status', 'LIKE', "$status");
    // }
    public function id($id)
    {
        $this->where('id', 'LIKE', "%$id%");
    }

    public function createdAt($date)
    {
        $startOfDay = $date . ' 00:00:00';
        $endOfDay = $date . ' 23:59:59';

        $this->whereBetween('created_at', [$startOfDay, $endOfDay]);
    }

    public function position($position)
    {
        $this->where('position', 'LIKE', "%{$position}%");
    }


    public function created_at($date)
    {
        $startOfDay = $date . ' 00:00:00';
        $endOfDay = $date . ' 23:59:59';

        $this->whereBetween('created_at', [$startOfDay, $endOfDay]);
    }
    /**
     * Related Models that have ModelFilters as well as the method on the ModelFilter
     * As [relationMethod => [input_key1, input_key2]].
     *
     * @var array
     */
    public $relations = [];
}
