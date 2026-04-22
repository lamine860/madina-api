<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\User;
use Modules\Shop\Models\Shop;
use Throwable;

final class ShopService
{
    private const LOGO_DIRECTORY = 'shops/logos';

    /**
     * @param  array<string, mixed>  $data  Champs validés (sans fichier logo).
     */
    public function createShop(User $user, array $data, ?UploadedFile $logo = null): Shop
    {
        $logoPath = null;

        if ($logo !== null) {
            try {
                $logoPath = $this->storeLogoFile($logo);
            } catch (Throwable $e) {
                report($e);
                throw $e;
            }
        }

        try {
            return DB::transaction(function () use ($user, $data, $logoPath): Shop {
                return Shop::query()->create([
                    'user_id' => $user->id,
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                    'description' => $data['description'] ?? null,
                    'logo_url' => $logoPath,
                    'company_name' => $data['company_name'] ?? null,
                    'vat_number' => $data['vat_number'] ?? null,
                    'is_verified' => false,
                ]);
            });
        } catch (Throwable $e) {
            if ($logoPath !== null) {
                $this->deleteStoredLogo($logoPath);
            }
            report($e);
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data  Champs validés (sans fichier logo).
     */
    public function updateShop(Shop $shop, array $data, ?UploadedFile $logo = null): Shop
    {
        $previousLogoPath = $shop->getRawOriginal('logo_url');
        $newLogoPath = null;

        if ($logo !== null) {
            try {
                $newLogoPath = $this->storeLogoFile($logo);
            } catch (Throwable $e) {
                report($e);
                throw $e;
            }
        }

        try {
            $updated = DB::transaction(function () use ($shop, $data, $newLogoPath): Shop {
                $payload = $data;

                if ($newLogoPath !== null) {
                    $payload['logo_url'] = $newLogoPath;
                }

                if ($payload !== []) {
                    $shop->update($payload);
                }

                return $shop->fresh() ?? $shop;
            });

            if ($newLogoPath !== null && $previousLogoPath !== null && $previousLogoPath !== '') {
                $this->deleteStoredLogo($previousLogoPath);
            }

            return $updated;
        } catch (Throwable $e) {
            if ($newLogoPath !== null) {
                $this->deleteStoredLogo($newLogoPath);
            }
            report($e);
            throw $e;
        }
    }

    /**
     * Stocke le fichier sur le disque public sous un nom dérivé d'un hachage.
     *
     * @return non-empty-string Chemin relatif au disque (ex. shops/logos/…)
     */
    private function storeLogoFile(UploadedFile $file): string
    {
        $absolutePath = $file->getRealPath();
        $hashSource = $absolutePath !== false
            ? hash_file('sha256', $absolutePath)
            : hash('sha256', (string) $file->getContent());

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?? 'bin');
        $filename = substr($hashSource, 0, 40).'.'.$extension;

        $storedPath = $file->storeAs(self::LOGO_DIRECTORY, $filename, 'public');

        if ($storedPath === false) {
            throw new \RuntimeException('Échec du stockage du logo sur le disque public.');
        }

        return $storedPath;
    }

    private function deleteStoredLogo(string $pathRelativeToDisk): void
    {
        if ($pathRelativeToDisk === '') {
            return;
        }

        try {
            Storage::disk('public')->delete($pathRelativeToDisk);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
