<?php

declare(strict_types=1);

namespace Modules\Shipping\DTOs;

final readonly class ShippingEstimationResult
{
    /**
     * @param  list<ShippingOptionEstimate>  $options
     */
    public function __construct(
        public ?string $zoneName,
        public ?string $resolvedNeighborhoodSlug,
        public array $options,
        public ?string $neighborhoodWarning,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'zone' => $this->zoneName,
            'neighborhood_slug' => $this->resolvedNeighborhoodSlug,
            'neighborhood_warning' => $this->neighborhoodWarning,
            'options' => array_map(static fn (ShippingOptionEstimate $o): array => $o->toArray(), $this->options),
        ];
    }
}
