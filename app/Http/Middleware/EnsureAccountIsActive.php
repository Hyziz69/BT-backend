<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->status === 'pending') {
            return response()->json([
                'message' => 'Váš účet čaká na schválenie administrátorom.',
                'status'  => 'pending',
            ], 403);
        }

        if ($user->status === 'rejected') {
            return response()->json([
                'message' => 'Váš účet bol zamietnutý. Kontaktujte NTI administráciu.',
                'status'  => 'rejected',
            ], 403);
        }

        return $next($request);
    }
}