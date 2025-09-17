<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\EnrollmentVerificationResource;
use App\Models\Application;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EnrollmentVerificationController extends BasicCrudController
{
    private $rules = [
        'verification_code' => 'required|string',
    ];

    public function show($verificationCode)
    {
        $enrollmentVerification = $this->queryBuilder()->where('verification_code', $verificationCode)->firstOrFail();
        return new EnrollmentVerificationResource($enrollmentVerification);
    }

    protected function model()
    {
        return Application::class;
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
        return EnrollmentVerificationResource::class;
    }
}
