<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckEmpresa
{
    public function handle(Request $request, Closure $next)
    {
        $usuario = Auth::user();

        if (!$usuario) {
            return response()->json(['error' => 'Usuário não autenticado'], 401);
        }

        return $next($request);
    }
}
