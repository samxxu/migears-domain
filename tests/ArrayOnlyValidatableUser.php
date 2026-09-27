<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use MiGears\Domain\Validatable;

/**
 * Fixture that uses Validatable WITHOUT DataAccess.
 *
 * Validatable declares toArray() abstract, so a class using it either pulls it
 * in with DataAccess or implements it itself — as this one does. Both the static
 * entry points (validateArray / isValidArray) and the instance ones (validate /
 * isValid) therefore work, with no dependency on DataAccess.
 */
final class ArrayOnlyValidatableUser
{
    use Validatable;

    public function __construct(public readonly int $value = 0) {}

    protected static function validationRules(): array
    {
        return ['value' => ['integer' => true]];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
