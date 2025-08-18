<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LoginHistoryController extends Controller
{
    public function updateLoginHistory(Request $request)
    {
        $user = $request->user();

        $loginHistory = LoginHistory::firstOrNew(['user_id' => $user->id]);

        if (!$loginHistory->first_login_at) {
            $loginHistory->first_login_at = Carbon::now();
        }

        $loginHistory->last_login_at = Carbon::now();
        $loginHistory->save();

        return response()->json([
            'success' => true,
            'message' => 'Histórico de login atualizado com sucesso',
            'data' => $loginHistory
        ]);
    }

    public function getAllUsersLoginHistory()
    {
        $loginHistory = LoginHistory::with('user:id,name,email')
            ->orderBy('last_login_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $loginHistory
        ]);
    }
}
