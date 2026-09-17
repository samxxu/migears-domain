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
 *   class UserDomain
 *   {
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
    private static ?Validator $validatorInstance = null;

    /**
     * Define validation rules for this domain class.
     *
     * Format: [fieldName => [ruleName => config, ...], ...]
     *
     * @return array<string, array<string, mixed>>
     */
    abstract protected static function validationRules(): array;

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
     * Get the shared Validator instance.
     */
    private static function getValidator(): Validator
    {
        if (self::$validatorInstance === null) {
            self::$validatorInstance = new Validator();
        }

        return self::$validatorInstance;
    }
}
