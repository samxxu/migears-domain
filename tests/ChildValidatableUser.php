<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

/**
 * Child domain that extends the parent and adds a custom rule via
 * customValidators() without reusing the Validatable trait directly.
 * Validator instances are cached per class (keyed by static::class), so this
 * child gets its own instance: its custom rule must be honoured even when the
 * parent has already initialised its own Validator.
 */
final class ChildValidatableUser extends ParentValidatableUser
{
    protected static function validationRules(): array
    {
        return ['value' => ['integer' => true, 'evenNumber' => true]];
    }

    protected static function customValidators(): array
    {
        return [EvenNumberValidator::class];
    }
}