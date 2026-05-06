<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::select('id', 'first_name', 'last_name', 'email', 'account_type', 'status', 'email_verified_at', 'created_at')
            ->get();

        return response()->json($users);
    }
}
