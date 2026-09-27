<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use MiGears\Domain\Validatable;

/**
 * Fixture that deliberately uses Validatable WITHOUT DataAccess.
 *
 * Only the static entry points (validateArray / isValidArray) are usable here:
 * the instance ones (validate / isValid) read the object through toArray(),
 * which DataAccess provides. ValidatableTest pins that boundary so it stays
 * visible rather than surfacing as a surprise at call time.
 */
final class ArrayOnlyValidatableUser
{
    use Validatable;

    public function __construct(public readonly int $value = 0) {}

    protected static function validationRules(): array
    {
        return ['value' => ['integer' => true]];
    }
}
