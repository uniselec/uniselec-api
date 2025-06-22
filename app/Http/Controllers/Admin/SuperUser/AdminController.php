<?php

namespace App\Http\Controllers\Admin\SuperUser;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use EloquentFilter\Filterable;
use Illuminate\Validation\Rule;

class AdminController extends BasicCrudController
{

    private $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:admins,email',
    ];


    public function resendPasswordResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:admins,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'E-mail inválido ou não encontrado.'], 422);
        }

        $email = $request->input('email');

        Password::broker('admins')->sendResetLink(
            ['email' => $email],
            function ($user, $token) {
                $frontendUrl = config('app.backoffice_url');
                $resetLink = "{$frontendUrl}/reset-password/{$token}/" . urlencode($user->email);

                $user->notify(new AdminResetPasswordNotification($resetLink));
            }
        );

        return response()->json(['message' => 'Link de redefinição reenviado com sucesso.']);
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', $this->defaultPerPage);
        $query = $this->queryBuilder();

        if (in_array(Filterable::class, class_uses($this->model()))) {
            $query = $query->filter($request->all());
        }

        $data = $request->has('all') || !$this->defaultPerPage
            ? $query->get()
            : $query->paginate($perPage);

        return $this->resourceCollection()::collection($data);
    }
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
        ]);

        $validatedData = $validator->validate();
        $temporaryPassword = Str::random(12);
        $validatedData['password'] = bcrypt($temporaryPassword);

        $admin = Admin::create($validatedData);

        $this->sendPasswordSetupNotification($admin);
        return response()->json(['data' => $admin], 201);
    }



    /**
     * Envia o e-mail para redefinição de senha.
     *
     * @param Admin $admin
     * @return void
     */
    protected function sendPasswordSetupNotification($admin)
    {
        Password::broker('admins')->sendResetLink(
            ['email' => $admin->email],
            function ($user, $token) {
                $frontendUrl = config('app.backoffice_url');
                $resetLink = "{$frontendUrl}/reset-password/{$token}/" . urlencode($user->email);
                $user->notify(new AdminResetPasswordNotification($resetLink));
            }
        );
    }
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

        $adminId = request()->id;

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('admins', 'email')->ignore($adminId, 'id'),
            ],
        ];
    }

    protected function resourceCollection()
    {
        return $this->resource();
    }

    protected function resource()
    {
        return AdminResource::class;
    }
}
