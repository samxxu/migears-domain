<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use MiGears\Domain\Validatable;

/**
 * Domain fixture that declares a custom validator via customValidators().
 */
final class EvenUser
{
    use Validatable;

    public function __construct(public readonly int $value = 0) {}

    protected static function validationRules(): array
    {
        return ['value' => ['evenNumber' => true]];
    }

    protected static function customValidators(): array
    {
        return [EvenNumberValidator::class];
    }
}