<?php

declare(strict_types=1);

namespace Modules\Catalog\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class ProductLinkedToOrdersException extends HttpException
{
    public function __construct()
    {
        parent::__construct(422, 'Ce produit est lié à des commandes et ne peut pas être supprimé.');
    }
}
