<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminAuth\LoginAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Http\Resources\AdminResource;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAdminAction $action): AdminResource
    {
        return new AdminResource($action->handle($request));
    }
}
