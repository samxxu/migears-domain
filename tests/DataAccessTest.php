<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use MiGears\Domain\DataAccess;
use PHPUnit\Framework\TestCase;

class DataAccessTest extends TestCase
{
    public function testFromArrayCreatesDomain(): void
    {
        $user = UserDomain::fromArray([
            'id' => 1,
            'user_name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $this->assertSame(1, $user->id);
        $this->assertSame('Alice', $user->user_name);
        $this->assertSame('alice@example.com', $user->email);
    }

    public function testToArrayReturnsAllProperties(): void
    {
        $user = new UserDomain(1, 'Alice', 'alice@example.com');

        $array = $user->toArray();

        $this->assertSame([
            'id' => 1,
            'user_name' => 'Alice',
            'email' => 'alice@example.com',
        ], $array);
    }

    public function testRoundTripFromArrayThenToArray(): void
    {
        $row = ['id' => 42, 'user_name' => 'Bob', 'email' => 'bob@example.com'];

        $domain = UserDomain::fromArray($row);
        $backToArray = $domain->toArray();

        $this->assertSame($row, $backToArray);
    }

    public function testFromArrayWithExtraKeysThrows(): void
    {
        $this->expectException(\Error::class);

        UserDomain::fromArray([
            'id' => 1,
            'user_name' => 'Alice',
            'email' => 'alice@example.com',
            'extra_field' => 'not in constructor',
        ]);
    }

    public function testFromArrayWithMissingKeysThrows(): void
    {
        $this->expectException(\Error::class);

        UserDomain::fromArray(['id' => 1]);
    }

    public function testPropertiesAreReadonly(): void
    {
        $user = new UserDomain(1, 'Alice', 'alice@example.com');

        $this->expectException(\Error::class);
        $user->id = 999;
    }
}

class UserDomain
{
    use DataAccess;

    public function __construct(
        public readonly int $id,
        public readonly string $user_name,
        public readonly string $email,
    ) {}
}
