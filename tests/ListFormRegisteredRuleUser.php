<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use MiGears\Domain\Validatable;

/**
 * References the injected rule in the zero-index list form (`[0 => 'uniqueEmail']`)
 * rather than the map form, to pin that both config styles resolve.
 */
final class ListFormRegisteredRuleUser
{
    use Validatable;

    public function __construct(public readonly string $email = '') {}

    protected static function validationRules(): array
    {
        return ['email' => [0 => 'uniqueEmail']];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
