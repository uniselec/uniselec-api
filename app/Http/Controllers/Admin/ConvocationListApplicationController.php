<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\ConvocationListApplicationResource;
use App\Models\ConvocationListApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use EloquentFilter\Filterable;
use ReflectionClass;
use Illuminate\Database\Eloquent\Builder;

class ConvocationListApplicationController extends BasicCrudController
{

    private $rules = [
        'convocation_list_id' => '',
        'application_id' => '',
        'course_id' => '',
        'admission_category_id' => '',
        'seat_id' => '',
        'ranking_at_generation' => '',
        'status' => '',
    ];

    public function index(Request $request)
    {
        // se quiser aumentar a memória só aqui
        ini_set('memory_limit', '512M');

        $perPage   = (int) $request->get('per_page', $this->defaultPerPage);
        $hasFilter = in_array(Filterable::class, class_uses($this->model()));
        $query     = $this->queryBuilder();

        if ($hasFilter) {
            $query = $query->filter($request->all());
        }

        $query->whereNotNull('created_at');

        // agora o $query já vem ordenado no queryBuilder()
        $items = $request->has('all') || ! $this->defaultPerPage
            ? $query->get()
            : $query->paginate($perPage);

        $resourceCollectionClass = $this->resourceCollection();
        $refClass                = new \ReflectionClass($resourceCollectionClass);

        if ($items instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            return $refClass->isSubclassOf(ResourceCollection::class)
                ? new $resourceCollectionClass($items)
                : $resourceCollectionClass::collection($items);
        }

        return new $resourceCollectionClass($items);
    }


    protected function model()
    {
        return ConvocationListApplication::class;
    }

    protected function rulesStore()
    {
        return $this->rules;
    }

    protected function rulesUpdate()
    {
        return $this->rules;
    }

    protected function resourceCollection()
    {
        return $this->resource();
    }

    protected function resource()
    {
        return ConvocationListApplicationResource::class;
    }
    protected function queryBuilder(): Builder
    {
        return parent::queryBuilder()
            ->with([
                'application:id,form_data',
                'course:id,name,modality,academic_unit',
                'category:id,name',
                'seat:id,seat_code,status',
                'application' => function ($q) {
                    $q->select('id', 'form_data', 'process_selection_id')
                        ->with([
                            'enemScore:id,application_id,scores,original_scores',
                            'applicationOutcome:id,application_id,status,classification_status,convocation_status,average_score,final_score,ranking,reason',
                        ]);
                },
            ])
            // garante que toda consulta use esta ordenação
            ->orderBy('course_id', 'asc')
            ->orderBy('admission_category_id', 'asc')
            ->orderBy('category_ranking', 'asc');
    }
}
