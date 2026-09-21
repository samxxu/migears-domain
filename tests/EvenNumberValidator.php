<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use MiGears\Validator\ValidatorInterface;

/**
 * Custom validator used to exercise Validatable::customValidators().
 * Rule alias derived from class name: evenNumber.
 */
final class EvenNumberValidator implements ValidatorInterface
{
    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        return is_numeric($value) && ((int) $value) % 2 === 0;
    }

    public function getErrorCode(): string
    {
        return 'evenNumber';
    }

    public function getErrorParams(): array
    {
        return [];
    }
}