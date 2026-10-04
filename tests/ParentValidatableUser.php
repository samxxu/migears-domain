<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use MiGears\Domain\Validatable;

/**
 * Parent fixture that references the custom `evenNumber` rule, registered by the
 * test rather than declared on the class. Used to prove a registration is scoped
 * to the class it was made on.
 */
class ParentValidatableUser
{
    use Validatable;

    public function __construct(public readonly int $value = 0) {}

    protected static function validationRules(): array
    {
        return ['value' => ['evenNumber' => true]];
    }

    /**
     * Validatable declares toArray() abstract; the parent supplies it, and the
     * child inherits it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
