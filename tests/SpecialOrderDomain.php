<?php

declare(strict_types=1);

namespace MiGears\Domain\Tests;

/**
 * Subclass that declares no loader of its own, used to pin the inheritance
 * semantics: it reads the loader registered on its parent.
 */
final class SpecialOrderDomain extends OrderDomain
{
}
