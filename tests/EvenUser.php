<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use MiGears\Domain\Validatable;

/**
 * Domain fixture whose `evenNumber` rule is registered (by class-string) in a
 * test via Validatable::register() rather than declared on the class.
 */
final class EvenUser
{
    use Validatable;

    public function __construct(public readonly int $value = 0) {}

    protected static function validationRules(): array
    {
        return ['value' => ['evenNumber' => true]];
    }

    /**
     * Validatable declares toArray() abstract; this fixture supplies it itself
     * rather than through DataAccess.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
