<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Requests\LoginRequest;
use Modules\Core\Http\Requests\RegisterRequest;
use Modules\Core\Http\Resources\UserResource;
use Modules\Core\Models\User;
use Modules\Core\Services\AuthService;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * Crée un compte client (utilisateur + profil `Customer`) et retourne un jeton Bearer Sanctum.
     *
     * @group Authentification
     *
     * @subgroup Inscription
     *
     * @unauthenticated
     *
     * @bodyParam email string required Adresse e-mail unique. Example: client@example.com
     * @bodyParam password string required Mot de passe. Example: Str0ng!Pass
     * @bodyParam password_confirmation string required Doit être identique à `password`. Example: Str0ng!Pass
     * @bodyParam first_name string required Prénom. Example: Amina
     * @bodyParam last_name string required Nom. Example: Diallo
     * @bodyParam phone string required Téléphone. Example: +221771234567
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $response = $this->authService->register($request->validated());

        return response()->json([
            'token' => $response->plainTextToken,
            'user' => new UserResource($response->user),
        ], 201);
    }

    /**
     * Authentifie un utilisateur actif et retourne un jeton Bearer Sanctum.
     *
     * @group Authentification
     *
     * @subgroup Connexion
     *
     * @unauthenticated
     *
     * @bodyParam email string required Example: client@example.com
     * @bodyParam password string required Example: Str0ng!Pass
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $response = $this->authService->login($request->validated());

        return response()->json([
            'token' => $response->plainTextToken,
            'user' => new UserResource($response->user),
        ]);
    }

    /**
     * Révoque le jeton d’accès API courant (celui utilisé dans l’en-tête `Authorization: Bearer`).
     *
     * @group Authentification
     *
     * @subgroup Session
     *
     * @authenticated
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authService->logout($user);

        return response()->json(['message' => __('Logged out successfully.')]);
    }

    /**
     * Retourne l’utilisateur authentifié avec le profil client et les adresses chargés.
     *
     * @group Authentification
     *
     * @subgroup Profil
     *
     * @authenticated
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['customer.addresses']);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }
}
