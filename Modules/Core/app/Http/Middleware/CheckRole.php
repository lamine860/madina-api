<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Entities\User;
use Symfony\Component\HttpFoundation\Response;

final class CheckRole
{
    /**
     * Comma-separated roles, e.g. middleware "role:admin,seller".
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $allowed = array_values(array_filter(array_map(trim(...), explode(',', $roles))));

        if (! in_array($user->role->value, $allowed, true)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
