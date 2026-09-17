<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use MiGears\Domain\DataAccess;
use MiGears\Domain\Validatable;

/**
 * Fixture domain class using both DataAccess and Validatable traits.
 */
class ValidatableUser
{
    use DataAccess;
    use Validatable;

    public function __construct(
        public readonly string $username = '',
        public readonly string $email = '',
        public readonly int $age = 0,
    ) {}

    protected static function validationRules(): array
    {
        return [
            'username' => ['required' => true, 'minLength' => 3, 'maxLength' => 20],
            'email'    => ['required' => true, 'email' => true],
            'age'      => ['integer' => true, 'min' => 0, 'max' => 150],
        ];
    }
}
