<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use MiGears\Domain\Validatable;

/**
 * References `uniqueEmail` but disables it with `false`; an injected instance
 * must not re-enable a rule the declaration turned off.
 */
final class DisabledRegisteredRuleUser
{
    use Validatable;

    public function __construct(public readonly string $email = '') {}

    protected static function validationRules(): array
    {
        return ['email' => ['uniqueEmail' => false]];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
