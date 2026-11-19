<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enmascarar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /** POST /api/v1/auth/login → 200 con token | 401 credenciales inválidas | 429 demasiados intentos */
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $usuario = User::where('email', $datos['email'])->first();

        if (! $usuario || ! Hash::check($datos['password'], $usuario->password)) {
            // Se registra el intento sin la contraseña y con el correo enmascarado.
            Log::channel('seguridad')->warning('Inicio de sesión fallido', [
                'email' => Enmascarar::correo($datos['email']),
                'ip' => $request->ip(),
            ]);

            // El mismo mensaje exista o no la cuenta: no revela qué correos están registrados.
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        $minutos = config('sanctum.expiration');
        $vence = $minutos ? now()->addMinutes($minutos) : null;
        $token = $usuario->createToken('api', ['*'], $vence);

        // Se registra el identificador del token, nunca el token.
        Log::channel('seguridad')->info('Inicio de sesión', [
            'usuario_id' => $usuario->id,
            'rol' => $usuario->role,
            'token_id' => $token->accessToken->id,
        ]);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $vence?->toIso8601String(),
            'user' => ['id' => $usuario->id, 'name' => $usuario->name, 'role' => $usuario->role],
        ]);
    }

    /** POST /api/v1/auth/logout → 200; el token usado deja de ser válido */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        Log::channel('seguridad')->info('Cierre de sesión', [
            'usuario_id' => $request->user()->id,
            'token_id' => $token->id,
        ]);

        $token->delete();

        return response()->json(['message' => 'Sesión cerrada: el token ya no es válido.']);
    }
}
