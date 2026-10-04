<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use MiGears\Domain\Validatable;

#[CoversClass(Validatable::class)]
#[UsesTrait(\MiGears\Domain\DataAccess::class)]
final class ValidatableTest extends TestCase
{
    // --- validate (instance method) ---

    public function testValidateReturnsEmptyArrayWhenValid(): void
    {
        $user = new ValidatableUser(
            username: 'alice',
            email: 'alice@example.com',
            age: 25,
        );

        self::assertSame([], $user->validate());
    }

    public function testValidateReturnsErrorForRequiredField(): void
    {
        $user = new ValidatableUser(
            username: '',
            email: 'alice@example.com',
            age: 25,
        );

        $errors = $user->validate();

        self::assertArrayHasKey('username', $errors);
        self::assertSame('required', $errors['username']['rule']);
        self::assertSame([], $errors['username']['params']);
    }

    public function testValidateReturnsErrorForMinLength(): void
    {
        $user = new ValidatableUser(
            username: 'ab',
            email: 'alice@example.com',
            age: 25,
        );

        $errors = $user->validate();

        self::assertArrayHasKey('username', $errors);
        self::assertSame('minLength', $errors['username']['rule']);
        self::assertSame(['min' => 3], $errors['username']['params']);
    }

    public function testValidateReturnsErrorForEmail(): void
    {
        $user = new ValidatableUser(
            username: 'alice',
            email: 'not-an-email',
            age: 25,
        );

        $errors = $user->validate();

        self::assertArrayHasKey('email', $errors);
        self::assertSame('email', $errors['email']['rule']);
    }

    public function testValidateReturnsErrorForMinValue(): void
    {
        $user = new ValidatableUser(
            username: 'alice',
            email: 'alice@example.com',
            age: -1,
        );

        $errors = $user->validate();

        self::assertArrayHasKey('age', $errors);
        self::assertSame('min', $errors['age']['rule']);
        self::assertSame(['min' => 0], $errors['age']['params']);
    }

    public function testValidateShortCircuitsOnFirstErrorPerField(): void
    {
        $user = new ValidatableUser(
            username: '',  // fails required, minLength not checked
            email: '',     // fails required, email not checked
            age: 0,
        );

        $errors = $user->validate();

        self::assertSame('required', $errors['username']['rule']);
        self::assertSame('required', $errors['email']['rule']);
    }

    public function testValidateReturnsMultipleFieldErrors(): void
    {
        $user = new ValidatableUser(
            username: 'ab',
            email: 'bad',
            age: 999,
        );

        $errors = $user->validate();

        self::assertCount(3, $errors);
        self::assertArrayHasKey('username', $errors);
        self::assertArrayHasKey('email', $errors);
        self::assertArrayHasKey('age', $errors);
    }

    // --- isValid ---

    public function testIsValidReturnsTrueWhenValid(): void
    {
        $user = new ValidatableUser(
            username: 'alice',
            email: 'alice@example.com',
            age: 25,
        );

        self::assertTrue($user->isValid());
    }

    public function testIsValidReturnsFalseWhenInvalid(): void
    {
        $user = new ValidatableUser(
            username: '',
            email: '',
            age: 0,
        );

        self::assertFalse($user->isValid());
    }

    // --- validateArray (static method) ---

    public function testValidateArrayReturnsEmptyWhenValid(): void
    {
        $data = [
            'username' => 'alice',
            'email' => 'alice@example.com',
            'age' => 25,
        ];

        self::assertSame([], ValidatableUser::validateArray($data));
    }

    public function testValidateArrayReturnsErrors(): void
    {
        $data = [
            'username' => 'ab',
            'email' => 'bad',
            'age' => -5,
        ];

        $errors = ValidatableUser::validateArray($data);

        self::assertCount(3, $errors);
        self::assertSame('minLength', $errors['username']['rule']);
        self::assertSame('email', $errors['email']['rule']);
        self::assertSame('min', $errors['age']['rule']);
    }

    // --- isValidArray ---

    public function testIsValidArrayReturnsTrueWhenValid(): void
    {
        $data = ['username' => 'alice', 'email' => 'a@b.com', 'age' => 20];
        self::assertTrue(ValidatableUser::isValidArray($data));
    }

    public function testIsValidArrayReturnsFalseWhenInvalid(): void
    {
        $data = ['username' => '', 'email' => '', 'age' => 0];
        self::assertFalse(ValidatableUser::isValidArray($data));
    }

    // --- register() ---

    public function testRegisteredRuleClassStringIsUsed(): void
    {
        EvenUser::register(EvenNumberRule::class);

        self::assertSame([], EvenUser::validateArray(['value' => 4]));

        $errors = EvenUser::validateArray(['value' => 3]);
        self::assertSame('evenNumber', $errors['value']['rule']);
    }

    public function testUnregisteredRuleIsUnknown(): void
    {
        // UnknownRuleUser references evenNumber but nothing registers it, so its
        // own Validator instance must not know the alias.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown rule: evenNumber');
        UnknownRuleUser::validateArray(['value' => 4]);
    }

    public function testRegisteredRuleInstanceIsUsed(): void
    {
        RegisteredRuleUser::register(self::uniqueEmailRule(['taken@example.com']));

        self::assertSame([], RegisteredRuleUser::validateArray(['email' => 'free@example.com']));

        $errors = RegisteredRuleUser::validateArray(['email' => 'taken@example.com']);
        self::assertSame('uniqueEmail', $errors['email']['rule']);

        // The instance entry point goes through the same Validator.
        self::assertFalse((new RegisteredRuleUser('taken@example.com'))->isValid());
    }

    public function testRegisteredRuleDoesNotLeakToOtherClass(): void
    {
        RegisteredRuleUser::register(self::uniqueEmailRule([]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown rule: uniqueEmail');
        UndeclaredRegisteredRuleUser::validateArray(['email' => 'free@example.com']);
    }

    public function testRegisteredRuleIsNotForcedWhereNotReferenced(): void
    {
        RegisteredRuleUser::register(self::uniqueEmailRule([]));
        // Registered but never referenced by this class's rules: it must not run.
        RegisteredRuleUser::register(EvenNumberRule::class);

        self::assertSame([], RegisteredRuleUser::validateArray(['email' => 'free@example.com']));
    }

    public function testDisabledRegisteredRuleStaysDisabled(): void
    {
        DisabledRegisteredRuleUser::register(self::uniqueEmailRule(['taken@example.com']));

        self::assertSame([], DisabledRegisteredRuleUser::validateArray(['email' => 'taken@example.com']));
    }

    public function testRegisteredRuleIsAppliedInListForm(): void
    {
        ListFormRegisteredRuleUser::register(self::uniqueEmailRule(['taken@example.com']));

        $errors = ListFormRegisteredRuleUser::validateArray(['email' => 'taken@example.com']);
        self::assertSame('uniqueEmail', $errors['email']['rule']);
    }

    /**
     * @param list<string> $taken
     */
    private static function uniqueEmailRule(array $taken): UniqueEmailRule
    {
        return new UniqueEmailRule(static fn (string $email): bool => in_array($email, $taken, true));
    }

    // --- per-class scoping ---

    public function testRegistrationIsScopedAndNotInherited(): void
    {
        // The child inherits validationRules() — it references evenNumber — but
        // no registration is inherited, so the alias is unknown to it.
        $this->assertRuleUnknown(static fn () => ChildValidatableUser::validateArray(['value' => 4]));

        // A registration on the parent serves the parent only ...
        ParentValidatableUser::register(EvenNumberRule::class);
        self::assertSame([], ParentValidatableUser::validateArray(['value' => 4]));

        // ... the child still does not see it ...
        $this->assertRuleUnknown(static fn () => ChildValidatableUser::validateArray(['value' => 4]));

        // ... until it registers the rule itself.
        ChildValidatableUser::register(EvenNumberRule::class);
        self::assertSame([], ChildValidatableUser::validateArray(['value' => 4]));
        self::assertSame('evenNumber', ChildValidatableUser::validateArray(['value' => 3])['value']['rule']);
    }

    /**
     * @param callable(): mixed $call
     */
    private function assertRuleUnknown(callable $call): void
    {
        try {
            $call();
            self::fail('Expected an unknown-rule InvalidArgumentException');
        } catch (\InvalidArgumentException $error) {
            self::assertStringContainsString('Unknown rule: evenNumber', $error->getMessage());
        }
    }

    // --- Integration with DataAccess ---

    public function testFromArrayAndValidate(): void
    {
        $user = ValidatableUser::fromArray([
            'username' => 'bob',
            'email' => 'bob@example.com',
            'age' => 30,
        ]);

        self::assertTrue($user->isValid());
    }

    public function testFromArrayInvalidData(): void
    {
        $user = ValidatableUser::fromArray([
            'username' => 'x',
            'email' => 'not-email',
            'age' => 200,
        ]);

        $errors = $user->validate();

        self::assertSame('minLength', $errors['username']['rule']);
        self::assertSame('email', $errors['email']['rule']);
        self::assertSame('max', $errors['age']['rule']);
    }

    // --- toArray() contract ---

    public function testToArrayIsDeclaredOnTheTraitSoTheDependencyIsEnforced(): void
    {
        // validate() reads the instance through toArray(); declaring it abstract
        // makes a class that lacks it fail at declaration rather than at call time.
        $method = (new \ReflectionClass(Validatable::class))->getMethod('toArray');

        self::assertTrue($method->isAbstract());
        self::assertSame(0, $method->getNumberOfParameters());
        self::assertNotNull($method->getReturnType());
        self::assertSame('array', (string) $method->getReturnType());
    }

    public function testClassSupplyingItsOwnToArrayValidatesWithoutDataAccess(): void
    {
        // ArrayOnlyValidatableUser uses Validatable alone and implements toArray()
        // itself; both entry points work with no DataAccess involved.
        self::assertSame([], ArrayOnlyValidatableUser::validateArray(['value' => 4]));
        self::assertTrue(ArrayOnlyValidatableUser::isValidArray(['value' => 4]));

        $valid = new ArrayOnlyValidatableUser(4);
        self::assertSame([], $valid->validate());
        self::assertTrue($valid->isValid());
    }
}
