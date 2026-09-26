<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use MiGears\Domain\DataAccess;

/**
 * Child record returned by OrderDomain::items() in the lazy-relation fixture.
 */
final class OrderItemDomain
{
    use DataAccess;

    public function __construct(
        public readonly int $id = 0,
        public readonly int $order_id = 0,
        public readonly string $sku = '',
    ) {}
}
