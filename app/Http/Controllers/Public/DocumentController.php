<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\BasicCrudController;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class DocumentController extends BasicCrudController
{
    private $rules = [
        'title' => 'required|max:255',
        'description' => 'required|max:255'
    ];

    /**
     * @OA\Get(
     *     path="/api/documents",
     *     summary="Get list of documents",
     *     tags={"Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Document"))
     *     )
     * )
     */
    public function index(Request $request)
    {
        return parent::index($request);
    }


    /**
     * @OA\Get(
     *     path="/api/documents/{id}",
     *     summary="Get a document by ID",
     *     tags={"Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Document")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Document not found"
     *     )
     * )
     */
    public function show($id)
    {
        return parent::show($id);
    }


    protected function model()
    {
        return Document::class;
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
        return DocumentResource::class;
    }
}
