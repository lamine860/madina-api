<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductVariant;
use Modules\Catalog\Exceptions\InsufficientStockException;
use Modules\Shop\Entities\Shop;
use Throwable;

final class ProductService
{
    /**
     * Crée un produit, ses variantes et attache la galerie média en une transaction.
     *
     * @param  array<string, mixed>  $productData  Champs produit validés (hors fichiers).
     * @param  list<array{sku: string, price: float|int|string, stock_qty: int, attributes?: array<string, mixed>}>  $variants
     * @param  list<UploadedFile>|null  $galleryFiles
     */
    public function createProductWithVariants(Shop $shop, array $productData, array $variants, ?array $galleryFiles = null): Product
    {
        try {
            return DB::transaction(function () use ($shop, $productData, $variants, $galleryFiles): Product {
                /** @var Product $product */
                $product = Product::query()->create([
                    'shop_id' => $shop->id,
                    'category_id' => $productData['category_id'],
                    'name' => $productData['name'],
                    'slug' => $productData['slug'],
                    'description' => $productData['description'] ?? null,
                    'base_price' => $productData['base_price'],
                    'is_active' => $productData['is_active'] ?? true,
                ]);

                foreach ($variants as $row) {
                    $attributes = $row['attributes'] ?? [];
                    $attributes = is_array($attributes)
                        ? $this->normalizeAttributeKeysToLowercase($attributes)
                        : [];

                    $product->variants()->create([
                        'sku' => $row['sku'],
                        'price' => $row['price'],
                        'stock_qty' => $row['stock_qty'],
                        'attributes' => $attributes,
                    ]);
                }

                if ($galleryFiles !== null) {
                    foreach ($galleryFiles as $file) {
                        $product->addMedia($file)->toMediaCollection('gallery');
                    }
                }

                return $product->load(['variants', 'category', 'shop', 'media']);
            });
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Met à jour le produit et synchronise les variantes (sans toucher au stock — utiliser {@see adjustStock}).
     *
     * @param  array<string, mixed>  $productFields  name, description, category_id, base_price, is_active (optionnel)
     * @param  list<array{id?: int|null, sku: string, price: mixed, attributes: array}>  $variantRows
     */
    public function updateProduct(Product $product, array $productFields, array $variantRows): Product
    {
        try {
            return DB::transaction(function () use ($product, $productFields, $variantRows): Product {
                /** @var Product $locked */
                $locked = Product::query()
                    ->whereKey($product->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $originalName = $locked->name;

                $locked->fill([
                    'name' => $productFields['name'],
                    'description' => $productFields['description'] ?? null,
                    'category_id' => $productFields['category_id'],
                    'base_price' => $productFields['base_price'],
                    'is_active' => array_key_exists('is_active', $productFields)
                        ? (bool) $productFields['is_active']
                        : $locked->is_active,
                ]);

                $nameChanged = $productFields['name'] !== $originalName;

                if ($nameChanged && $this->shouldRegenerateProductSlug($locked)) {
                    $locked->slug = Str::slug($productFields['name']);
                }

                $locked->save();

                $syncedIds = [];

                foreach ($variantRows as $row) {
                    $payload = [
                        'sku' => $row['sku'],
                        'price' => $row['price'],
                        'attributes' => $this->normalizeAttributeKeysToLowercase($row['attributes'] ?? []),
                    ];

                    if (! empty($row['id'])) {
                        /** @var ProductVariant|null $variant */
                        $variant = ProductVariant::withTrashed()
                            ->where('product_id', $locked->id)
                            ->whereKey((int) $row['id'])
                            ->lockForUpdate()
                            ->first();

                        if ($variant === null) {
                            throw (new ModelNotFoundException)->setModel(ProductVariant::class, [(int) $row['id']]);
                        }

                        if ($variant->trashed()) {
                            $variant->restore();
                        }

                        $variant->fill($payload);
                        $variant->save();
                    } else {
                        /** @var ProductVariant|null $variant */
                        $variant = ProductVariant::withTrashed()
                            ->where('product_id', $locked->id)
                            ->where('sku', $row['sku'])
                            ->lockForUpdate()
                            ->first();

                        if ($variant === null) {
                            $variant = $locked->variants()->create(array_merge($payload, [
                                'stock_qty' => 0,
                            ]));
                        } else {
                            if ($variant->trashed()) {
                                $variant->restore();
                            }
                            $variant->fill($payload);
                            $variant->save();
                        }
                    }

                    $syncedIds[] = $variant->id;
                }

                $locked->variants()
                    ->whereNotIn('id', $syncedIds)
                    ->get()
                    ->each(static function (ProductVariant $orphan): void {
                        $orphan->delete();
                    });

                return $locked->fresh(['variants', 'category', 'shop', 'media']) ?? $locked;
            });
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Slug : régénération sur changement de nom uniquement si le produit est inactif (brouillon) ou créé depuis au moins 24h,
     * afin de limiter les changements d’URL sur les fiches actives récentes (SEO).
     */
    private function shouldRegenerateProductSlug(Product $product): bool
    {
        if (! $product->is_active) {
            return true;
        }

        if ($product->created_at === null) {
            return true;
        }

        return $product->created_at->lessThanOrEqualTo(now()->subHours(24));
    }

    /**
     * Ajuste le stock d’une variante (positif = entrée, négatif = sortie). Ne pas utiliser pour la mise à jour catalogue générale.
     *
     * @throws InsufficientStockException
     * @throws ModelNotFoundException
     */
    public function adjustStock(int $variantId, int $amount): void
    {
        if ($amount === 0) {
            return;
        }

        if ($amount > 0) {
            $this->incrementStock($variantId, $amount);

            return;
        }

        $this->decrementStock($variantId, abs($amount));
    }

    /**
     * Indique si la variante existe et dispose d’au moins {@see $quantity} unités en stock.
     */
    public function hasStock(int $variantId, int $quantity): bool
    {
        if ($quantity < 0) {
            return false;
        }

        $variant = ProductVariant::query()->find($variantId);

        if ($variant === null) {
            return false;
        }

        return $variant->stock_qty >= $quantity;
    }

    /**
     * Diminue le stock disponible (soustraction uniquement sur la ligne variante ; aucune autre persistance métier).
     *
     * @throws InsufficientStockException
     * @throws ModelNotFoundException
     */
    public function decrementStock(int $variantId, int $quantity): void
    {
        $this->assertStrictlyPositiveQuantity($quantity);

        try {
            DB::transaction(function () use ($variantId, $quantity): void {
                /** @var ProductVariant|null $variant */
                $variant = ProductVariant::query()
                    ->whereKey($variantId)
                    ->lockForUpdate()
                    ->first();

                if ($variant === null) {
                    throw (new ModelNotFoundException)->setModel(ProductVariant::class, [$variantId]);
                }

                if ($variant->stock_qty < $quantity) {
                    throw new InsufficientStockException(
                        variantId: $variantId,
                        requestedQuantity: $quantity,
                        availableQuantity: $variant->stock_qty,
                    );
                }

                $previous = $variant->stock_qty;
                $variant->stock_qty = $previous - $quantity;
                $variant->save();

                Log::info('catalog.stock.movement', $this->stockMovementLogContext(
                    action: 'decrement',
                    variant: $variant,
                    quantity: $quantity,
                    previousStock: $previous,
                    newStock: $variant->stock_qty,
                ));
            });
        } catch (InsufficientStockException|ModelNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Augmente le stock disponible (réapprovisionnement ou retour de stock). Verrouillage pessimiste pour cohérence concurrente.
     *
     * @throws ModelNotFoundException
     */
    public function incrementStock(int $variantId, int $quantity): void
    {
        $this->assertStrictlyPositiveQuantity($quantity);

        try {
            DB::transaction(function () use ($variantId, $quantity): void {
                /** @var ProductVariant $variant */
                $variant = ProductVariant::query()
                    ->whereKey($variantId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $previous = $variant->stock_qty;
                $variant->stock_qty = $previous + $quantity;
                $variant->save();

                Log::info('catalog.stock.movement', $this->stockMovementLogContext(
                    action: 'increment',
                    variant: $variant,
                    quantity: $quantity,
                    previousStock: $previous,
                    newStock: $variant->stock_qty,
                ));
            });
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * @return array<string, int|string|null>
     */
    private function stockMovementLogContext(
        string $action,
        ProductVariant $variant,
        int $quantity,
        int $previousStock,
        int $newStock,
    ): array {
        return [
            'action' => $action,
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'sku' => $variant->sku,
            'quantity' => $quantity,
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
        ];
    }

    private function assertStrictlyPositiveQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité doit être un entier strictement positif.');
        }
    }

    /**
     * Force toutes les clés d’objets JSON en minuscules (récursif) pour des requêtes et index cohérents côté PostgreSQL.
     *
     * @param  array<int|string, mixed>  $data
     * @return array<int|string, mixed>
     */
    private function normalizeAttributeKeysToLowercase(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $key = mb_strtolower($key, 'UTF-8');
            }

            if (is_array($value)) {
                $normalized[$key] = $this->normalizeAttributeKeysToLowercase($value);

                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
