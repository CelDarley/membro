<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;

class GoogleAuthController extends Controller
{
	/**
	 * Redireciona para o Google OAuth
	 */
	public function redirectToGoogle()
	{
		try {
			return Socialite::driver('google')->stateless()->redirect();
		} catch (\Exception $e) {
			Log::error('Erro ao redirecionar para Google OAuth: ' . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => 'Erro ao conectar com Google'
			], 500);
		}
	}

	/**
	 * Callback do Google OAuth
	 */
	public function handleGoogleCallback()
	{
		try {
			$googleUser = Socialite::driver('google')->stateless()->user();
			
			Log::info('Usuário Google autenticado:', [
				'email' => $googleUser->getEmail(),
				'name' => $googleUser->getName(),
				'google_id' => $googleUser->getId()
			]);

			// Verificar se o usuário já existe
			$user = User::where('email', $googleUser->getEmail())->first();

			if (!$user) {
				// Criar novo usuário
				$user = User::create([
					'name' => $googleUser->getName(),
					'email' => $googleUser->getEmail(),
					'google_id' => $googleUser->getId(),
					'email_verified_at' => now(), // Google já verifica o email
					'password' => Hash::make(Str::random(16)), // Senha aleatória
					'avatar' => $googleUser->getAvatar(),
				]);

				Log::info('Novo usuário criado via Google OAuth:', ['user_id' => $user->id]);
			} else {
				// Atualizar dados do usuário existente
				$user->update([
					'google_id' => $googleUser->getId(),
					'avatar' => $googleUser->getAvatar(),
					'name' => $googleUser->getName(),
				]);

				Log::info('Usuário existente atualizado via Google OAuth:', ['user_id' => $user->id]);
			}

			// Gerar token de autenticação
			$token = $user->createToken('google-auth-token')->plainTextToken;

			// Atualizar histórico de login
			try {
				$user->loginHistories()->create([
					'ip_address' => request()->ip(),
					'user_agent' => request()->userAgent(),
					'login_at' => now(),
					'login_method' => 'google_oauth'
				]);
			} catch (\Exception $e) {
				Log::warning('Erro ao salvar histórico de login: ' . $e->getMessage());
			}

			return response()->json([
				'success' => true,
				'message' => 'Login realizado com sucesso via Google',
				'token' => $token,
				'user' => [
					'id' => $user->id,
					'name' => $user->name,
					'email' => $user->email,
					'role' => $user->role,
					'avatar' => $user->avatar,
				]
			]);

		} catch (\Exception $e) {
			Log::error('Erro no callback do Google OAuth: ' . $e->getMessage());
			
			return response()->json([
				'success' => false,
				'message' => 'Erro ao autenticar com Google: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Verificar status da conexão com Google
	 */
	public function checkGoogleConnection()
	{
		try {
			$config = config('services.google');
			
			return response()->json([
				'success' => true,
				'configured' => !empty($config['client_id']) && !empty($config['client_secret']),
				'redirect_uri' => $config['redirect'] ?? null
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Erro ao verificar configuração do Google'
			], 500);
		}
	}
} 