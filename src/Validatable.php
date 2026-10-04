<?php

declare(strict_types=1);

namespace MiGears\Domain;

use MiGears\Validator\RuleInterface;
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
 * Instance-level validation reads the object through `toArray()`, so this trait
 * declares it abstract: a class that uses `Validatable` must supply `toArray()`,
 * either by using the `DataAccess` trait or by implementing it itself. The
 * static `validateArray()` / `isValidArray()` methods still read an array
 * directly, but the class they belong to has to satisfy the same contract.
 *
 * Custom rules are registered per class through `register()`, in one of two
 * forms:
 *
 *  - a rule class-string — the normal form. The engine builds the rule from the
 *    field's config in `validationRules()`, so the rule's parameters belong in
 *    the rules table, never at the registration:
 *        UserDomain::register(StrengthRule::class);
 *        // 'password' => ['strength' => 12]  →  new StrengthRule(12)
 *
 *  - a ready-made instance — the escape hatch, only for a rule that needs a
 *    dependency the Domain must not hold (a DAO-backed uniqueness rule, say).
 *    Its alias is its own getErrorCode(), and a Manager registers it once from
 *    its constructor, mirroring the lazy-relation `setItemLoader()` pattern:
 *        UserDomain::register(new UniqueEmailRule($this->dao));
 *
 * Never use the instance form to carry parameters: it is already built, so the
 * config written for it in `validationRules()` is ignored.
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
     * Per-class Validator instances, keyed by class name.
     *
     * A trait static property belongs to the class that uses the trait and is
     * shared by its subclasses, so this single map serves the whole hierarchy.
     * It is keyed by static::class for two reasons, neither optional:
     *
     *  1. Scoping — a rule a class registers stays with that class; it never
     *     reaches a sibling, nor leaks from a parent into its children.
     *  2. Caching and persistence — the Validator is built once per class,
     *     and it is the thing a registration is written into: a throwaway
     *     instance would lose every registered rule.
     *
     * The two simpler shapes both fail. A single shared instance would leak every
     * class's rules into every other. An instance property cannot be used at all:
     * toArray() is get_object_vars($this), so a stored Validator would be handed
     * to the DAO as a column — the same trap the lazy-relation loader avoids by
     * living in a static slot. Static, per-class storage is the only shape that
     * satisfies both.
     *
     * @var array<class-string, Validator>
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
     * The object state to validate, as a field-name keyed array.
     *
     * Declared abstract because validate() and isValid() read the instance
     * through it: a class using this trait must provide it, so the dependency is
     * a compile-time contract instead of an undefined-method error at call time.
     * DataAccess supplies a get_object_vars()-based implementation; a class that
     * does not use it may implement its own.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * Register a custom rule for this domain class.
     *
     * Two forms, for two different jobs:
     *
     *  1. A rule class-string — the normal form. Its alias is derived from the
     *     class name, and the engine builds the rule lazily from the field's
     *     config in validationRules(), so the rule's parameters belong in the
     *     rules table:
     *
     *       UserDomain::register(StrengthRule::class);
     *       // 'password' => ['strength' => 12]  →  new StrengthRule(12)
     *       // 'pin'      => ['strength' => 4]   →  new StrengthRule(4)
     *
     *  2. A ready-made RuleInterface instance — the escape hatch, only for a rule
     *     that needs a dependency the Domain must not hold (e.g. a DAO-backed
     *     uniqueness rule). Its alias is its own getErrorCode(), and a Manager
     *     registers it once from its constructor:
     *
     *       UserDomain::register(new UniqueEmailRule($this->dao));
     *
     * Do not use form 2 to pass parameters: an instance is already built, so the
     * config written for it in validationRules() is ignored. Either form applies
     * only where validationRules() references the alias — registering does not by
     * itself put the rule on any field. Re-registering an alias replaces the
     * rule, and reports true when it displaced a rule that was already reachable
     * (a sibling registration or a built-in).
     *
     * @param class-string<RuleInterface>|RuleInterface $rule
     */
    public static function register(string|RuleInterface $rule): bool
    {
        return self::getValidator()->register($rule);
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
        return self::getValidator()->validate($this->toArray(), static::validationRules());
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
        return self::getValidator()->validate($data, static::validationRules());
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
     * Get the calling class's Validator, building and caching it on first use.
     *
     * Keyed by static::class — not self::class — so a subclass inheriting this
     * storage gets an instance of its own rather than the parent's. See the
     * $validatorInstances docblock for why that scoping is required.
     */
    private static function getValidator(): Validator
    {
        $class = static::class;

        if (!isset(self::$validatorInstances[$class])) {
            self::$validatorInstances[$class] = new Validator();
        }

        return self::$validatorInstances[$class];
    }
}
