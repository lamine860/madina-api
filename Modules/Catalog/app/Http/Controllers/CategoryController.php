<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Catalog\Http\Requests\StoreCategoryRequest;
use Modules\Catalog\Http\Requests\UpdateCategoryRequest;
use Modules\Catalog\Http\Resources\CategoryResource;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Services\CategoryService;

final class CategoryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    /**
     * @group Catalogue
     *
     * @subgroup Catégories
     *
     * Liste les catégories racines avec leurs sous-catégories (arbre, profondeur limitée côté serveur).
     */
    public function index(): JsonResponse
    {
        $categories = $this->categoryService->listRootCategoriesWithNestedChildren();

        return response()->json([
            'categories' => CategoryResource::collection($categories),
        ]);
    }

    /**
     * @group Catalogue
     *
     * @subgroup Catégories
     *
     * Affiche une catégorie avec son fil d’Ariane (racine → … → catégorie courante) et ses enfants directs.
     */
    public function show(Category $category): JsonResponse
    {
        $category->load(['children' => static fn ($q) => $q->orderBy('name')]);

        $breadcrumb = $this->categoryService->breadcrumbFor($category);

        return response()->json([
            'category' => new CategoryResource($category),
            'breadcrumb' => CategoryResource::collection($breadcrumb),
        ]);
    }

    /**
     * @group Catalogue
     *
     * @subgroup Catégories
     *
     * @authenticated
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = $this->categoryService->createCategory($request->validated());

        return response()->json([
            'category' => new CategoryResource($category),
        ], 201);
    }

    /**
     * @group Catalogue
     *
     * @subgroup Catégories
     *
     * @authenticated
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $updated = $this->categoryService->updateCategory($category, $request->validated());

        return response()->json([
            'category' => new CategoryResource($updated),
        ]);
    }

    /**
     * @group Catalogue
     *
     * @subgroup Catégories
     *
     * @authenticated
     */
    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $this->categoryService->deleteCategory($category);

        return response()->json(null, 204);
    }
}
