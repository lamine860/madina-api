<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Http\Resources\CategoryResource;

final class CategoryController extends Controller
{
    /**
     * @group Catalogue
     *
     * @subgroup Catégories
     */
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->orderBy('name')
            ->get();

        return response()->json([
            'categories' => CategoryResource::collection($categories),
        ]);
    }
}
