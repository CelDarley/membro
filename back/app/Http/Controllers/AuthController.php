<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use App\Models\LoginHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\TwoFactorCodeMail;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        if ($user->two_factor_enabled) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $user->two_factor_code = $code;
            $user->two_factor_expires_at = Carbon::now()->addMinutes(10);
            $user->save();

            try {
                Mail::to($user->email)->send(new TwoFactorCodeMail($code));
            } catch (\Throwable $e) {
                // Em ambientes locais sem mail configurado, apenas ignore o erro
            }

            return response()->json([
                'requires_two_factor' => true,
                'message' => 'Código de verificação enviado para o seu e-mail'
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $loginHistory = LoginHistory::firstOrNew(['user_id' => $user->id]);
        if (!$loginHistory->first_login_at) {
            $loginHistory->first_login_at = Carbon::now();
        }
        $loginHistory->last_login_at = Carbon::now();
        $loginHistory->save();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ],
            'token' => $token,
            'token_type' => 'Bearer'
        ]);
    }

    public function verifyTwoFactor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user || !$user->two_factor_enabled) {
            return response()->json(['message' => '2FA não habilitado ou usuário inválido'], 400);
        }

        if (!$user->two_factor_code || !$user->two_factor_expires_at || Carbon::now()->greaterThan($user->two_factor_expires_at)) {
            return response()->json(['message' => 'Código expirado ou inexistente'], 400);
        }

        if (hash_equals($user->two_factor_code, $request->code) === false) {
            return response()->json(['message' => 'Código inválido'], 400);
        }

        $user->two_factor_code = null;
        $user->two_factor_expires_at = null;
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        $loginHistory = LoginHistory::firstOrNew(['user_id' => $user->id]);
        if (!$loginHistory->first_login_at) {
            $loginHistory->first_login_at = Carbon::now();
        }
        $loginHistory->last_login_at = Carbon::now();
        $loginHistory->save();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ],
            'token' => $token,
            'token_type' => 'Bearer'
        ]);
    }

    public function enableTwoFactor(Request $request)
    {
        $user = $request->user();
        $user->two_factor_enabled = true;
        $user->save();
        return response()->json(['message' => 'Autenticação de dois fatores ativada']);
    }

    public function disableTwoFactor(Request $request)
    {
        $user = $request->user();
        $user->two_factor_enabled = false;
        $user->two_factor_code = null;
        $user->two_factor_expires_at = null;
        $user->save();
        return response()->json(['message' => 'Autenticação de dois fatores desativada']);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user'
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ],
            'token' => $token,
            'token_type' => 'Bearer'
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout realizado com sucesso']);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'role' => $request->user()->role,
                'two_factor_enabled' => (bool)$request->user()->two_factor_enabled,
                'phone' => $request->user()->phone,
            ]
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Link de recuperação enviado'])
            : response()->json(['message' => 'Erro ao enviar link'], 400);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Senha alterada com sucesso'])
            : response()->json(['message' => 'Erro ao alterar senha'], 400);
    }
}
