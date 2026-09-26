<?php

declare(strict_types=1);

namespace MiGears\Domain;

/**
 * Provides array-to-object and object-to-array conversion.
 *
 * Domain classes use this trait to gain fromArray() and toArray()
 * without any base class inheritance or external hydrator.
 *
 *   use MiGears\Domain\DataAccess;
 *
 *   class UserDomain
 *   {
 *       use DataAccess;
 *
 *       public function __construct(
 *           public readonly int $id,
 *           public readonly string $user_name,
 *           public readonly string $email,
 *       ) {}
 *   }
 *
 *   $user = UserDomain::fromArray($row);   // array → Domain
 *   $row = $user->toArray();               // Domain → array
 */
trait DataAccess
{
    /**
     * Creates a Domain instance from a database row (associative array).
     *
     * Uses PHP 8.x named arguments via array spreading, so column names
     * must match constructor parameter names exactly.
     *
     * No type coercion is performed: each value must already match the type
     * declared by the corresponding constructor parameter. PDO supplies those
     * native types itself (since PHP 8.1), so neither the Domain nor the DAO
     * needs a casting layer. A missing key, extra key, or type mismatch throws
     * a native \Error or \TypeError — loud on purpose, so schema drift surfaces
     * instead of being silently coerced.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): static
    {
        return new static(...$row);
    }

    /**
     * Converts the Domain instance to an associative array.
     *
     * Property names match database column names 1:1 (no camelCase conversion).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
