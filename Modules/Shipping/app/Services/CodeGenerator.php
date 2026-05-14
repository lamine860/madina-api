<?php

declare(strict_types=1);

namespace Modules\Shipping\Services;

use Modules\Shipping\Models\Shipment;

final class CodeGenerator
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function __construct(
        private readonly int $length = 6,
    ) {}

    public function generateUniqueExitCode(): string
    {
        return $this->generateUnique('exit_code');
    }

    public function generateUniqueConfirmationCode(): string
    {
        return $this->generateUnique('confirmation_code');
    }

    private function generateUnique(string $column): string
    {
        for ($i = 0; $i < 50; $i++) {
            $code = $this->randomCode();
            if (! Shipment::query()->where($column, $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Impossible de générer un code unique.');
    }

    private function randomCode(): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < $this->length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
