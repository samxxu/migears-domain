<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use MiGears\Domain\Validatable;

/**
 * References `uniqueEmail` exactly like RegisteredRuleUser, but nothing injects
 * the rule for this class — used to prove a per-class registration does not leak
 * across classes.
 */
final class UndeclaredRegisteredRuleUser
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
