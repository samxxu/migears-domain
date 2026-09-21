<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

/**
 * Child domain that extends the parent and adds a custom rule via
 * customValidators() without reusing the Validatable trait directly.
 * Under the current implementation the parent's Validator instance is shared.
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