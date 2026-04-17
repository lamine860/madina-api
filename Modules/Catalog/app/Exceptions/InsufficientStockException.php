<?php

declare(strict_types=1);

namespace Modules\Catalog\Exceptions;

use Exception;
use Throwable;

/**
 * Levée lorsqu'une décrémentation de stock dépasse la quantité disponible.
 * Réutilisable par les autres modules (ex. commandes).
 */
final class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $requestedQuantity,
        public readonly int $availableQuantity,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        if ($message === '') {
            $message = sprintf(
                'Stock insuffisant pour la variante %d : demandé %d, disponible %d.',
                $variantId,
                $requestedQuantity,
                $availableQuantity
            );
        }

        parent::__construct($message, $code, $previous);
    }
}
