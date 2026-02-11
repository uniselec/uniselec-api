<?php


namespace App\ModelFilters;

use Illuminate\Database\Eloquent\Builder;

class ApplicationFilter extends DefaultModelFilter
{
    protected $sortable = ['created_at'];
    protected $drop_id = false;
    public function search($name)
    {
        // Use JSON_UNQUOTE and JSON_EXTRACT to search the "name" attribute within form_data JSON
        $this->where(function (Builder $query) use ($name) {
            $query->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(form_data, '$.name')) LIKE ?",
                ["%{$name}%"]
            );
        });
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
