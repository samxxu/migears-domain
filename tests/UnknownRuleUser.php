<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use MiGears\Domain\Validatable;

/**
 * Domain fixture that references a custom rule WITHOUT declaring the custom
 * validator, used to prove customValidators() does not leak across classes.
 */
final class UnknownRuleUser
{
    use Validatable;

    public function __construct(public readonly int $value = 0) {}

    protected static function validationRules(): array
    {
        return ['value' => ['evenNumber' => true]];
    }

    /**
     * Validatable declares toArray() abstract; this fixture supplies it itself
     * rather than through DataAccess.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}