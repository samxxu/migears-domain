<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Covers the recommended lazy-relation pattern: a static loader injected
 * before construction, with the Domain itself staying persistence-free.
 */
final class LazyRelationTest extends TestCase
{
    protected function tearDown(): void
    {
        // Static state outlives a single test, so always reset it.
        OrderDomain::setItemLoader(null);
    }

    public function testItemsThrowsWhenLoaderWasNeverInjected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OrderDomain::setItemLoader() was not called');

        (new OrderDomain(1))->items();
    }

    public function testItemsUsesTheInjectedLoader(): void
    {
        OrderDomain::setItemLoader(fn(OrderDomain $order) => [
            new OrderItemDomain(1, $order->id, 'sku-a'),
            new OrderItemDomain(2, $order->id, 'sku-b'),
        ]);

        $items = (new OrderDomain(7))->items();

        self::assertCount(2, $items);
        self::assertSame('sku-a', $items[0]->sku);
        self::assertSame(7, $items[0]->order_id);
    }

    public function testLoaderIsInjectedBeforeConstruction(): void
    {
        OrderDomain::setItemLoader(fn(OrderDomain $order) => [
            new OrderItemDomain(1, $order->id, 'sku-a'),
        ]);

        // constructed after the injection
        self::assertSame('sku-a', (new OrderDomain(3))->items()[0]->sku);

        // and again for an instance built later through fromArray()
        $order = OrderDomain::fromArray(['id' => 4, 'user_id' => 1, 'title' => 'T']);
        self::assertSame(4, $order->items()[0]->order_id);
    }

    public function testLoaderReceivesTheDomainInstance(): void
    {
        // the loader branches on user_id, so it must receive the instance itself
        OrderDomain::setItemLoader(
            fn(OrderDomain $order) => $order->user_id === 42
                ? [new OrderItemDomain(1, $order->id, 'vip')]
                : []
        );

        self::assertSame('vip', (new OrderDomain(1, 42))->items()[0]->sku);
        self::assertSame([], (new OrderDomain(2, 7))->items());
    }

    public function testStaticLoaderDoesNotLeakIntoToArray(): void
    {
        OrderDomain::setItemLoader(fn() => []);

        $order = new OrderDomain(5, 9, 'Order E');

        self::assertSame(
            ['id' => 5, 'user_id' => 9, 'title' => 'Order E'],
            $order->toArray()
        );
    }

    public function testFromArrayRoundTripIsUnaffectedByTheLoader(): void
    {
        OrderDomain::setItemLoader(fn(OrderDomain $order) => [
            new OrderItemDomain(1, $order->id, 'sku-a'),
        ]);

        $row = ['id' => 8, 'user_id' => 2, 'title' => 'Order F'];
        $order = OrderDomain::fromArray($row);

        self::assertSame($row, $order->toArray());
        self::assertSame(8, $order->items()[0]->order_id);
    }

    public function testPassingNullResetsTheStaticState(): void
    {
        OrderDomain::setItemLoader(fn() => []);

        OrderDomain::setItemLoader(null);

        $this->expectException(RuntimeException::class);
        (new OrderDomain(1))->items();
    }

    public function testSubclassInheritsTheParentLoader(): void
    {
        OrderDomain::setItemLoader(fn(OrderDomain $order) => [
            new OrderItemDomain(1, $order->id, 'inherited'),
        ]);

        $special = new SpecialOrderDomain(11, 1, 'Special');

        self::assertSame('inherited', $special->items()[0]->sku);
        self::assertSame(
            ['id' => 11, 'user_id' => 1, 'title' => 'Special'],
            $special->toArray()
        );
    }
}
