<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use MiGears\Domain\Validatable;

/**
 * Domain fixture whose `uniqueEmail` rule cannot be declared statically — it
 * needs a lookup — so it must be injected as an instance via
 * Validatable::register(), standing in for a Manager wired with a DAO.
 */
final class RegisteredRuleUser
{
    use Validatable;

    public function __construct(public readonly string $email = '') {}

    protected static function validationRules(): array
    {
        return ['email' => ['required' => true, 'uniqueEmail' => true]];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
