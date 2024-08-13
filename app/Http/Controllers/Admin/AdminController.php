<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminCollection;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends BasicCrudController
{
    private $rules = [
        'name' => 'required|max:255',
        'password' => 'required|min:6',
        'email' => 'required|min:6',
    ];

    protected function model()
    {
        return Admin::class;
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
        return AdminResource::class;
    }

    protected function handlePassword($request)
    {
        if ($request->has('password')) {
            $request->merge(['password' => Hash::make($request->password)]);
        }
    }

    public function store(Request $request)
    {
        $this->handlePassword($request);
        return parent::store($request);
    }

    public function update(Request $request, $id)
    {
        $this->handlePassword($request);
        return parent::update($request, $id);
    }
}
