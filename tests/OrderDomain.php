<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use Closure;
use MiGears\Domain\DataAccess;
use RuntimeException;

/**
 * Fixture demonstrating the recommended lazy-relation pattern.
 *
 * The related-data loader is a static member injected BEFORE construction,
 * normally by the Manager through OrderDomain::setItemLoader(). The Domain
 * holds no DAO, no Manager and no instance state; the loader is invoked with
 * the current instance, so it may use any of its fields.
 */
class OrderDomain
{
    use DataAccess;

    /** @var null|Closure(self): list<OrderItemDomain> */
    private static ?Closure $itemLoader = null;

    public function __construct(
        public readonly int $id = 0,
        public readonly int $user_id = 0,
        public readonly string $title = '',
    ) {}

    /**
     * Injects the related-data loader.
     *
     * Passing null resets the static state, which keeps tests isolated.
     */
    public static function setItemLoader(?callable $loader): void
    {
        static::$itemLoader = $loader === null ? null : Closure::fromCallable($loader);
    }

    /**
     * Related records, loaded through the injected callable.
     *
     * @return list<OrderItemDomain>
     */
    public function items(): array
    {
        if (static::$itemLoader === null) {
            throw new RuntimeException('OrderDomain::setItemLoader() was not called');
        }

        return (static::$itemLoader)($this);
    }
}
