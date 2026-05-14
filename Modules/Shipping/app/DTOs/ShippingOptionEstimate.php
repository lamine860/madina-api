<?php

declare(strict_types=1);

namespace Modules\Shipping\DTOs;

final readonly class ShippingOptionEstimate
{
    public function __construct(
        public string $code,
        public string $name,
        public string $price,
        public int $etaMinMinutes,
        public int $etaMaxMinutes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'price' => $this->price,
            'eta_min_minutes' => $this->etaMinMinutes,
            'eta_max_minutes' => $this->etaMaxMinutes,
        ];
    }
}
