<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

/**
 * Child that inherits ParentValidatableUser's validationRules() (referencing
 * `evenNumber`) and toArray(), but declares nothing of its own. Used to prove a
 * rule registered on the parent does not reach the child, and that the child can
 * register the rule for itself.
 */
final class ChildValidatableUser extends ParentValidatableUser
{
}
