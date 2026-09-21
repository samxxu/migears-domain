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

    // --- customValidators hook ---

    public function testCustomValidatorFromHookIsUsed(): void
    {
        self::assertSame([], EvenUser::validateArray(['value' => 4]));

        $errors = EvenUser::validateArray(['value' => 3]);
        self::assertSame('evenNumber', $errors['value']['rule']);
    }

    public function testCustomValidatorsDoNotLeakToUndeclaredClass(): void
    {
        // UnknownRuleUser references the evenNumber rule but does not declare
        // the custom validator, so its own Validator instance must not know it.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown validator: evenNumber');
        UnknownRuleUser::validateArray(['value' => 4]);
    }

    // --- Parent/child inheritance ---

    public function testChildCustomValidatorHonouredEvenWhenParentInitialisedFirst(): void
    {
        // The parent initialises the shared Validator first (no custom rules).
        // The child extends the parent and adds evenNumber via customValidators().
        ParentValidatableUser::validateArray(['value' => 4]);

        self::assertSame([], ChildValidatableUser::validateArray(['value' => 4]));

        $errors = ChildValidatableUser::validateArray(['value' => 3]);
        self::assertSame('evenNumber', $errors['value']['rule']);
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
}
