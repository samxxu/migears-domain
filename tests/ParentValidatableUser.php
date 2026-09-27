<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use MiGears\Domain\Validatable;

/**
 * Inheritable fixture with a built-in rule only. Used to prove that a child
 * class's customValidators() is honoured even when the parent has already
 * initialised its own Validator: instances are cached per static::class, so the
 * child gets its own rather than sharing the parent's.
 */
class ParentValidatableUser
{
    use Validatable;

    public function __construct(public readonly int $value = 0) {}

    protected static function validationRules(): array
    {
        return ['value' => ['integer' => true]];
    }
}