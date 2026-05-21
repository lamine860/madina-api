<?php

declare(strict_types=1);

namespace Modules\Notification\Services;

use Modules\Notification\Exceptions\OrangeSmsException;

final class PhoneNormalizer
{
    /**
     * Normalise un numéro Guinée au format 224XXXXXXXXX (sans +).
     *
     * @throws OrangeSmsException
     */
    public function normalize(string $input): string
    {
        $digits = preg_replace('/\D+/', '', trim($input)) ?? '';

        if ($digits === '') {
            throw new OrangeSmsException('Numéro de téléphone invalide.');
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '224') && strlen($digits) === 12) {
            return $this->assertGuineaFormat($digits);
        }

        if (strlen($digits) === 9) {
            return $this->assertGuineaFormat('224'.$digits);
        }

        throw new OrangeSmsException('Le numéro doit être au format Guinée (224 + 9 chiffres).');
    }

    /**
     * Formate pour l’API Orange SMS (tel:+224…).
     */
    public function toTelUri(string $normalized): string
    {
        return 'tel:+'.$this->normalize($normalized);
    }

    /**
     * @throws OrangeSmsException
     */
    private function assertGuineaFormat(string $digits): string
    {
        if (! preg_match('/^224\d{9}$/', $digits)) {
            throw new OrangeSmsException('Le numéro doit être au format Guinée (224 + 9 chiffres).');
        }

        return $digits;
    }
}
