<?php

declare(strict_types=1);

namespace Modules\Reviews\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Reviews\Http\Requests\UpdateProductReviewRequest;
use Modules\Reviews\Http\Resources\ProductReviewResource;
use Modules\Reviews\Models\ProductReview;
use Modules\Reviews\Services\ReviewService;

final class ReviewManagementController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ReviewService $reviewService,
    ) {}

    /**
     * Met à jour un avis du client connecté.
     *
     * @group Avis
     *
     * @subgroup Gestion
     *
     * @authenticated
     */
    public function update(UpdateProductReviewRequest $request, ProductReview $review): JsonResponse
    {
        $this->authorize('update', $review);

        $validated = $request->validated();

        $updated = $this->reviewService->updateReview(
            $review,
            (int) ($validated['rating'] ?? $review->rating),
            array_key_exists('comment', $validated) ? $validated['comment'] : $review->comment,
        );

        return response()->json([
            'review' => new ProductReviewResource($updated),
        ]);
    }

    /**
     * Supprime un avis du client connecté.
     *
     * @group Avis
     *
     * @subgroup Gestion
     *
     * @authenticated
     */
    public function destroy(ProductReview $review): JsonResponse
    {
        $this->authorize('delete', $review);

        $this->reviewService->deleteReview($review);

        return response()->json(null, 204);
    }
}
