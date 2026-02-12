<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use Illuminate\Validation\Rule;

class DocumentController extends BasicCrudController
{
    private $rules = [
        'title' => 'required|max:255',
        'description' => 'required|max:255'
    ];


    public function index(Request $request)
    {
        return parent::index($request);
    }

    public function store(Request $request)
    {
        $file = $request->file('file');

        if ($file) {
            $request->merge([
                'filename' => $file->getClientOriginalName(),
            ]);
        }

        $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('documents', 'title')
                ->where(fn ($query) => $query->where('process_selection_id', $request->process_selection_id)),
            ],

            'file' => 'required|file|max:5120', // 5MB

            'filename' => [
                'required',
                'string',
                Rule::unique('documents', 'filename')
                ->where(fn ($query) => $query->where('process_selection_id', $request->process_selection_id)),
            ],

            'status' => 'in:draft,published,archived',
            'process_selection_id' => 'required|exists:process_selections,id',
        ],[
            'title.unique' => 'Já existe um documento com este título neste processo seletivo.',
            'filename.unique' => 'Já existe um documento com este nome de arquivo neste processo seletivo.'  
        ]);

        $filename = $request->filename; 

        $path = $file->store('documents', 'public');

        $document = Document::create([
            'title' => $request->title,
            'description' => $request->description,
            'path' => $path,
            'filename' => $filename,
            'status' => $request->status ?? 'draft',
            'process_selection_id' => $request->process_selection_id,
        ]);

        return new DocumentResource($document);
    }

    public function update(Request $request, $id)
    {
        $document = Document::findOrFail($id);

        $process_selection_id = $request->process_selection_id ?? $document->process_selection_id;

        $request->validate([
            'title' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('documents', 'title')
                    ->where(fn ($q) => $q->where('process_selection_id', $process_selection_id))
                    ->ignore($document->id),
            ],
            'status' => 'in:draft,published,archived',
            'process_selection_id' => 'sometimes|exists:process_selections,id',
        ], [
            'title.unique' => 'Já existe um documento com este título neste processo seletivo.',
        ]);

        $document->update($request->only(['title', 'description', 'status', 'process_selection_id']));

        return new DocumentResource($document);
    }
    public function updateStatus(Request $request, $id)
    {
        $document = Document::findOrFail($id);

        $request->validate([
            'status' => 'required|in:draft,published,archived'
        ]);

        $document->update(['status' => $request->status]);

        return response()->json(['message' => 'Status atualizado com sucesso', 'data' => new DocumentResource($document)]);
    }

    public function show($id)
    {
        return parent::show($id);
    }


    public function destroy($id)
    {
        return parent::destroy($id);
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
