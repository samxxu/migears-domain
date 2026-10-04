<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use Closure;
use MiGears\Validator\RuleInterface;

/**
 * Dependency-bearing rule: it can only answer "is this email taken?" through the
 * lookup its owner supplies, which is exactly the kind of rule a Manager injects
 * via Validatable::register() rather than a class-string.
 * Rule alias derived from the class name: uniqueEmail.
 */
final class UniqueEmailRule implements RuleInterface
{
    /** @param Closure(string): bool $emailExists */
    public function __construct(private readonly Closure $emailExists) {}

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return !($this->emailExists)((string) $value);
    }

    public function getErrorCode(): string
    {
        return 'uniqueEmail';
    }

    public function getErrorParams(): array
    {
        return [];
    }
}
