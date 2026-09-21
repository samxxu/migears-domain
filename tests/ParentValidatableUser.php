<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use MiGears\Domain\Validatable;

/**
 * Inheritable fixture with a built-in rule only. Used to prove whether a child
 * class's customValidators() is honoured when the parent initialises the
 * shared Validator first.
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