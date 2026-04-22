<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Exceptions\CategoryNotEmptyException;
use Throwable;

final class CategoryService
{
    private const NESTED_CHILDREN_DEPTH = 12;

    /**
     * Catégories racines avec arbre d’enfants (profondeur limitée pour l’API).
     *
     * @return Collection<int, Category>
     */
    public function listRootCategoriesWithNestedChildren(): Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->with(self::nestedChildrenEagerLoad(self::NESTED_CHILDREN_DEPTH))
            ->orderBy('name')
            ->get();
    }

    /**
     * Ancêtres du plus haut niveau vers le parent direct, puis la catégorie courante (fil d’Ariane complet).
     *
     * @return BaseCollection<int, Category>
     */
    public function breadcrumbFor(Category $category): BaseCollection
    {
        $orderedAncestorIds = [];
        $node = $category;

        while ($node->parent_id !== null) {
            array_unshift($orderedAncestorIds, $node->parent_id);
            $parent = Category::query()->find($node->parent_id);
            if ($parent === null) {
                break;
            }
            $node = $parent;
        }

        if ($orderedAncestorIds === []) {
            return new BaseCollection([$category]);
        }

        /** @var BaseCollection<int, Category> $ancestors */
        $ancestors = Category::query()
            ->whereIn('id', $orderedAncestorIds)
            ->get()
            ->sortBy(static function (Category $c) use ($orderedAncestorIds): int {
                $pos = array_search($c->id, $orderedAncestorIds, true);

                return $pos === false ? 0 : (int) $pos;
            })
            ->values();

        return $ancestors->push($category);
    }

    /**
     * @param  array{name: string, parent_id?: int|null}  $data
     */
    public function createCategory(array $data): Category
    {
        try {
            return DB::transaction(function () use ($data): Category {
                $slug = $this->uniqueSlugFromName($data['name']);

                /** @var Category $category */
                $category = Category::query()->create([
                    'name' => $data['name'],
                    'slug' => $slug,
                    'parent_id' => $data['parent_id'] ?? null,
                ]);

                return $category->fresh();
            });
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * @param  array{name: string, slug?: string|null, parent_id?: int|null}  $data
     */
    public function updateCategory(Category $category, array $data): Category
    {
        try {
            return DB::transaction(function () use ($category, $data): Category {
                /** @var Category $locked */
                $locked = Category::query()
                    ->whereKey($category->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $locked->name = $data['name'];

                if (array_key_exists('slug', $data) && $data['slug'] !== null && $data['slug'] !== '') {
                    $locked->slug = $this->ensureUniqueSlug((string) $data['slug'], $locked->id);
                } else {
                    $locked->slug = $this->uniqueSlugFromName($data['name'], $locked->id);
                }

                if (array_key_exists('parent_id', $data)) {
                    $locked->parent_id = $data['parent_id'];
                }

                $locked->save();

                return $locked->fresh() ?? $locked;
            });
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function deleteCategory(Category $category): void
    {
        try {
            DB::transaction(function () use ($category): void {
                /** @var Category $locked */
                $locked = Category::query()
                    ->whereKey($category->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->children()->exists()) {
                    throw CategoryNotEmptyException::hasChildren();
                }

                if ($locked->products()->exists()) {
                    throw CategoryNotEmptyException::hasProducts();
                }

                $locked->delete();
            });
        } catch (CategoryNotEmptyException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * @return list<int>
     */
    public function descendantIds(Category $category): array
    {
        $ids = [];
        $stack = [$category->id];

        while ($stack !== []) {
            /** @var int $id */
            $id = array_pop($stack);
            $childIds = Category::query()
                ->where('parent_id', $id)
                ->pluck('id')
                ->map(static fn ($cid): int => (int) $cid)
                ->all();

            foreach ($childIds as $childId) {
                $ids[] = $childId;
                $stack[] = $childId;
            }
        }

        return $ids;
    }

    private function uniqueSlugFromName(string $name, ?int $ignoreId = null): string
    {
        return $this->ensureUniqueSlug(Str::slug($name), $ignoreId);
    }

    private function ensureUniqueSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = $baseSlug !== '' ? $baseSlug : 'categorie';
        $candidate = $slug;
        $suffix = 1;

        while (Category::query()
            ->where('slug', $candidate)
            ->when($ignoreId !== null, static fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private static function nestedChildrenEagerLoad(int $depth): array
    {
        if ($depth <= 0) {
            return [];
        }

        return [
            'children' => static function ($query) use ($depth): void {
                $query->orderBy('name')->with(self::nestedChildrenEagerLoad($depth - 1));
            },
        ];
    }
}
