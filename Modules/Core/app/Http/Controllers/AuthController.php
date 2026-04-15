<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Entities\User;
use Modules\Core\Http\Requests\LoginRequest;
use Modules\Core\Http\Requests\RegisterRequest;
use Modules\Core\Http\Resources\UserResource;
use Modules\Core\Services\AuthService;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $response = $this->authService->register($request->validated());

        return response()->json([
            'token' => $response->plainTextToken,
            'user' => new UserResource($response->user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $response = $this->authService->login($request->validated());

        return response()->json([
            'token' => $response->plainTextToken,
            'user' => new UserResource($response->user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authService->logout($user);

        return response()->json(['message' => __('Logged out successfully.')]);
    }

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
