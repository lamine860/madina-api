<?php

declare(strict_types=1);

namespace Modules\Cart\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Cart\Http\Requests\StoreWishlistRequest;
use Modules\Cart\Http\Resources\WishlistResource;
use Modules\Cart\Models\Wishlist;
use Modules\Cart\Services\WishlistService;

final class WishlistController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly WishlistService $wishlistService,
    ) {}

    /**
     * @group Liste de souhaits
     *
     * @subgroup Articles
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        $rows = $this->wishlistService->listForUser($request->user());

        return response()->json([
            'items' => WishlistResource::collection($rows),
        ]);
    }

    /**
     * @group Liste de souhaits
     *
     * @subgroup Articles
     *
     * @authenticated
     */
    public function store(StoreWishlistRequest $request): JsonResponse
    {
        $this->authorize('create', Wishlist::class);

        $validated = $request->validated();
        $row = $this->wishlistService->add($request->user(), (int) $validated['product_variant_id']);

        return response()->json([
            'item' => new WishlistResource($row),
        ], 201);
    }

    /**
     * @group Liste de souhaits
     *
     * @subgroup Articles
     *
     * @authenticated
     */
    public function destroy(Request $request, int $wishlist): JsonResponse
    {
        $row = Wishlist::query()
            ->whereKey($wishlist)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $this->authorize('delete', $row);

        $this->wishlistService->remove($row);

        return response()->json(null, 204);
    }
}
