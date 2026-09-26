<?php

declare(strict_types=1);

namespace MiGears\Domain;

use MiGears\Validator\Validator;

/**
 * Adds self-validation capability to domain objects.
 *
 * Domain classes using this trait define their validation rules
 * via the static `validationRules()` method, and can validate
 * their current state with `validate()`.
 *
 * Errors are returned as structured error codes + params,
 * ready for i18n translation.
 *
 * Usage:
 *   use MiGears\Domain\DataAccess;
 *   use MiGears\Domain\Validatable;
 *
 *   class UserDomain
 *   {
 *       use DataAccess;
 *       use Validatable;
 *
 *       public function __construct(
 *           public readonly string $username,
 *           public readonly string $email,
 *       ) {}
 *
 *       protected static function validationRules(): array
 *       {
 *           return [
 *               'username' => ['required' => true, 'minLength' => 3],
 *               'email'    => ['required' => true, 'email' => true],
 *           ];
 *       }
 *   }
 *
 *   $user = new UserDomain('a', 'invalid');
 *   $errors = $user->validate();
 *   // ['username' => ['rule' => 'minLength', 'params' => ['min' => 3]],
 *   //  'email'    => ['rule' => 'email', 'params' => []]]
 */
trait Validatable
{
    /**
     * Validator instances keyed by class name.
     *
     * Trait static properties are copied only to the class that uses the trait
     * and are shared by its subclasses. Keeping a per-class map (instead of a
     * single cached instance) guarantees each concrete class gets its own
     * Validator seeded with its own customValidators(), even when the parent
     * initialises first.
     */
    private static array $validatorInstances = [];

    /**
     * Define validation rules for this domain class.
     *
     * Format: [fieldName => [ruleName => config, ...], ...]
     *
     * @return array<string, array<string, mixed>>
     */
    abstract protected static function validationRules(): array;

    /**
     * Optional custom validators to pre-register on this domain class's
     * shared Validator instance.
     *
     * Each entry is a validator class-string (e.g.
     * `StrongPasswordValidator::class`); the rule alias is derived from the
     * class name. Override in the domain class to add rules beyond the built-in
     * set. Defaults to none.
     *
     * @return list<class-string<\MiGears\Validator\ValidatorInterface>>
     */
    protected static function customValidators(): array
    {
        return [];
    }

    /**
     * Validate the current object state against the defined rules.
     *
     * Returns an empty array if all validations pass.
     * Each error has 'rule' (error code) and 'params' for i18n interpolation.
     *
     * @return array<string, array{rule: string, params: array<string, mixed>}>
     */
    public function validate(): array
    {
        $data = $this->toArray();
        $rules = static::validationRules();

        return self::getValidator()->validate($data, $rules);
    }

    /**
     * Validate an array of data against this domain's rules.
     *
     * Useful for pre-validating input before constructing the domain object.
     *
     * @param array<string, mixed> $data
     * @return array<string, array{rule: string, params: array<string, mixed>}>
     */
    public static function validateArray(array $data): array
    {
        $rules = static::validationRules();

        return self::getValidator()->validate($data, $rules);
    }

    /**
     * Check if the current object state is valid.
     */
    public function isValid(): bool
    {
        return $this->validate() === [];
    }

    /**
     * Check if an array of data would be valid for this domain.
     *
     * @param array<string, mixed> $data
     */
    public static function isValidArray(array $data): bool
    {
        return static::validateArray($data) === [];
    }

    /**
     * Get the Validator instance for the calling class.
     *
     * Keyed by static::class so subclasses do not share a parent's seeded
     * instance within an inheritance chain.
     */
    private static function getValidator(): Validator
    {
        $class = static::class;

        if (!isset(self::$validatorInstances[$class])) {
            self::$validatorInstances[$class] = new Validator(static::customValidators());
        }

        return self::$validatorInstances[$class];
    }
}
