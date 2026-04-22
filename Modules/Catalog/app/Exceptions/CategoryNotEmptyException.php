<?php

declare(strict_types=1);

namespace Modules\Catalog\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class CategoryNotEmptyException extends HttpException
{
    public static function hasChildren(): self
    {
        return new self(409, 'La catégorie ne peut pas être supprimée car elle possède des sous-catégories.');
    }

    public static function hasProducts(): self
    {
        return new self(409, 'La catégorie ne peut pas être supprimée car elle est liée à des produits.');
    }
}
