# migears/domain

![Version](https://img.shields.io/badge/version-2.0.0-blue)

Minimalist Domain layer — pure data containers with zero mapping.

> **Background**: miGears is the open-source successor of **TinyGears**, a
> self-developed PHP framework. It was renamed and open-sourced recently because
> the name *TinyGears* is already taken in the open-source community.

## Philosophy

- **No Getter/Setter** — properties are `public readonly`
- **No Hydrator/Mapper** — direct `new XxxDomain(...$row)` construction
- **No base class inheritance** — use the `DataAccess` trait
- **Field names match database columns 1:1** — no camelCase conversion
- **No persistence logic** — Domain knows nothing about SQL or DAO

## Boundaries

**In scope**

- The `DataAccess` trait: `fromArray()` (array → Domain) and `toArray()` (Domain → array), binding column names to properties 1:1 with no camelCase conversion (PSR-4 root `MiGears\Domain`).
- The `Validatable` trait: per-class rules via `validationRules()`, `validate()` / `isValid()` / `validateArray()` / `isValidArray()`, and a per-class `register()` for custom rules (class-strings or injected instances).
- Domain objects as plain `public readonly` data carriers, plus the recommended static lazy-relation accessor pattern (`setItemLoader()`), which keeps the Domain free of DAO/Manager references.

**Not in scope (by design)**

- Persistence: the Domain knows nothing about SQL or DAO — generating statements belongs to `migears/sql`, executing them and converting rows belongs to `migears/dao`.
- The validation rule set itself: `Validatable` only declares rules and delegates to the shared `Validator`; the built-in rules and rule execution belong to `migears/validator`, and turning the returned error codes into text belongs to `migears/i18n`.
- Hydration, mapping and scalar conversion: there is no hydrator/mapper and no casting layer; PDO (PHP 8.1+) already delivers native `int`/`float`/`string`/`null`, so `fromArray()` binds by parameter name only.
- Wiring the lazy loader and injected validation rules: calling `setItemLoader()` / `register()` from a Manager constructor is `migears/manager`'s job — there is no lifecycle hook here.

## Installation

```bash
composer require migears/domain
```

Requires: PHP 8.1+, `migears/validator`.

## Quick Start

### Define a Domain

```php
use MiGears\Domain\DataAccess;

class UserDomain
{
    use DataAccess;

    public function __construct(
        public readonly int $id,
        public readonly string $user_name,
        public readonly string $email,
        public readonly int $age,
        public readonly string $created_at,
    ) {}
}
```

### Array → Domain

```php
// From a database row
$row = ['id' => 1, 'user_name' => 'Alice', 'email' => 'a@b.com', 'age' => 25, 'created_at' => '2024-01-01'];

$user = UserDomain::fromArray($row);
echo $user->user_name;  // "Alice"
```

`fromArray()` uses PHP 8.x named arguments via `...$row` array spreading, so array keys must match constructor parameter names exactly.

### Domain → Array

```php
$row = $user->toArray();
// ['id' => 1, 'user_name' => 'Alice', 'email' => 'a@b.com', 'age' => 25, 'created_at' => '2024-01-01']
```

`toArray()` uses `get_object_vars($this)`, returning all properties as an associative array.

### Round Trip

```php
$domain = UserDomain::fromArray($row);
$back = $domain->toArray();
// $back === $row  ✅  (same key order)
```

`===` also requires an identical key order: `get_object_vars()` returns properties in
declaration order, so a `SELECT *` whose column order differs from the constructor's
parameter order makes `===` false while `==` stays true. Either list the columns
explicitly in the query, or compare with `==`.

## Type Contract

`fromArray()` binds values via PHP 8.x **strict typed named arguments**, so no type coercion happens here. Every value must already be of the type declared by the constructor parameter:

- an `int $id` parameter requires a genuine PHP `int`, not the string `'1'`
- a missing key, an extra key, or a type mismatch throws a native `\Error` / `\TypeError`

Native typing is **not** something the Domain — or the DAO — has to produce; PDO
already does it. Since PHP 8.1 a result set carries real PHP `int` / `float` for
numeric columns, under both emulated and native prepares and on every bundled
driver. The value chain is therefore:

```
PDO (PHP 8.1+) → native PHP types (int, float, string, null)
                   ↓
  Domain::fromArray() only binds the row by parameter name (no hydration, no casting)
```

This is why neither the Domain nor the DAO needs a hydrator or scalar-conversion
logic.

Three things to watch for:

- `DECIMAL` columns stay `string` (precision-preserving) — declare them `string`
- `TINYINT(1)` columns are `int` — declare them `int`, not `bool`
- never enable `PDO::ATTR_STRINGIFY_FETCHES`: it restores the old
  string-everything behaviour and breaks every `int` property

A mismatch between a constructor type and its column fails **loudly** with a
native `\TypeError` rather than converting silently — that is intentional: it
surfaces schema drift on the spot instead of coercing `'2024-01-01'` into `2024`.

## Naming Convention

| Layer | Convention | Example |
|-------|-----------|---------|
| Database column | snake_case | `user_name` |
| Domain property | snake_case (matches column) | `$user_name` |
| Constructor param | snake_case (matches column) | `string $user_name` |

No camelCase ↔ snake_case conversion anywhere. What you see in the database is what you see in code.

## Architecture

Domain is the middle layer of miGears' three-layer data architecture:

```
Service Layer (business logic)
    ↓ calls
DAO Layer (receives/returns Domain objects) → migears/dao
    ↓ internally calls
SQL Layer (SQL + params → arrays) → migears/sql
    ↓
PDO / MySQL
```

- Domain knows nothing about SQL or DAO
- DAO uses `fromArray()` to convert SQL results into Domain objects
- DAO uses `toArray()` to convert Domain objects back to arrays for SQL

## Related Data (Lazy Loading)

Domain objects stay pure: they never hold a DAO, a Manager, or any other module
reference. When business code still wants `$order->items()`, the recommended
practice is a **static loader injected before construction** — the Domain declares
the accessor, the Manager supplies the implementation. All dependency knowledge
therefore stays in the Manager, and the Domain remains independently testable.

### Manager side

```php
final class OrderManager
{
    public function __construct(private OrderItemDao $itemDao)
    {
        OrderDomain::setItemLoader(
            fn(OrderDomain $order) => $this->itemDao->getByOrderId($order->id)
        );
    }
}
```

There is no lifecycle hook here and nothing calls a `boot()` for you: the wiring builds
each Manager once, before anything is served, so the constructor is the place
(see `migears/manager`).

### Domain side

```php
use Closure;
use MiGears\Domain\DataAccess;
use RuntimeException;

class OrderDomain
{
    use DataAccess;

    /** @var null|Closure(self): list<OrderItemDomain> */
    private static ?Closure $itemLoader = null;

    public function __construct(
        public readonly int $id,
        public readonly string $title,
    ) {}

    public static function setItemLoader(?callable $loader): void
    {
        static::$itemLoader = $loader === null ? null : Closure::fromCallable($loader);
    }

    /** @return list<OrderItemDomain> */
    public function items(): array
    {
        if (static::$itemLoader === null) {
            throw new RuntimeException('OrderDomain::setItemLoader() was not called');
        }

        return (static::$itemLoader)($this);
    }
}
```

```php
$order = OrderDomain::fromArray($row);
$order->items();     // loaded through the Manager's callable
$order->toArray();   // ['id' => ..., 'title' => ...] — loader not included
```

### Why not store the loader on the instance?

Because any instance property leaks into persistence. `toArray()` is
`get_object_vars($this)`, so a stored callable — **even a `private` one** — ends up
in the array the DAO hands to SQL:

```
toArray() → ['id' => 7, 'title' => 'Order A', 'itemsLoader' => Closure]
SQL       → ERROR: table orders has no column named itemsLoader
```

Static storage keeps `fromArray()` and `toArray()` untouched.

### Rules

- Declare the slot as `?Closure`. A property typed `callable` is a **fatal error**
  (`Property ... cannot have type callable`); accept `callable` in the setter and
  normalise with `Closure::fromCallable()`.
- Accept `?callable` and treat `null` as reset. Static state outlives a single
  test, so `tearDown()` should call `setItemLoader(null)`.
- Throw when the loader was never injected. Returning `[]` silently would make
  "no related records" and "not configured" indistinguishable.
- Use `static::` rather than `self::` so the accessor stays overridable.
- A subclass that declares no slot of its own inherits its parent's loader; to get
  an independent one it must declare its own slot and setter.
- This is deliberate global state — the only place this package recommends it.
  Inject once, from a single place: the Manager's constructor.

### When to use it

Use it for related records and child collections that you do not want to load
eagerly and do not want the Domain to know how to fetch. Do not use it for plain
column reads — those already live on the object — and do not use it when callers
need different loaders for the same class; that is a sign the caller should ask
the Manager instead.

## Self-Validation with Validatable

Domain objects can validate their own data using the `Validatable` trait. Validation rules are defined in the domain class itself, and errors are returned as structured error codes + params (i18n-ready).

Depends on `migears/validator`.

`Validatable` declares `toArray(): array` abstract, because `validate()` and
`isValid()` read the instance through it. A class using `Validatable` must
therefore supply `toArray()` — through `DataAccess`, as below, or by implementing
it itself.

```php
use MiGears\Domain\DataAccess;
use MiGears\Domain\Validatable;

class UserDomain
{
    use DataAccess;
    use Validatable;

    public function __construct(
        public readonly string $username,
        public readonly string $email,
        public readonly int $age = 0,
    ) {}

    protected static function validationRules(): array
    {
        return [
            'username' => ['required' => true, 'minLength' => 3, 'maxLength' => 20],
            'email'    => ['required' => true, 'email' => true],
            'age'      => ['integer' => true, 'min' => 0, 'max' => 150],
        ];
    }
}
```

### Validate an instance

```php
$user = new UserDomain('ab', 'invalid', -1);

$errors = $user->validate();
// [
//   'username' => ['rule' => 'minLength', 'params' => ['min' => 3]],
//   'email'    => ['rule' => 'email', 'params' => []],
//   'age'      => ['rule' => 'min', 'params' => ['min' => 0]],
// ]

$user->isValid(); // false
```

### Validate before construction

Raw input such as `$_POST` is all strings, and `fromArray()` refuses to coerce
(see Type Contract), so it cannot build a domain that declares a non-string field
— `int $age` handed `'25'` throws a `TypeError`. Validate the array first, then
construct:

```php
$errors = UserDomain::validateArray($_POST);

if ($errors === []) {
    $user = UserDomain::fromArray($_POST);
}
```

### Custom validation rules

A custom rule is registered with `register()`, in one of two forms.

**By class-string — the normal form.** The alias is derived from the class name,
and the engine builds the rule from the field's config in `validationRules()`. A
rule's parameters therefore belong in the rules table, not at the registration:

```php
use MiGears\Validator\RuleInterface;

final class StrengthRule implements RuleInterface
{
    public function __construct(private int $min = 8) {}
    public function validate(mixed $value): bool
    {
        return is_string($value) && strlen($value) >= $this->min;
    }
    public function getErrorCode(): string { return 'strength'; }
    public function getErrorParams(): array { return ['min' => $this->min]; }
}

UserDomain::register(StrengthRule::class);

// in validationRules():
'password' => ['strength' => 12],   // → new StrengthRule(12)
'pin'      => ['strength' => 4],    // → new StrengthRule(4)
```

One class, a different parameter per field. Registration is per class, so a rule
registered on one domain class never leaks into another. `register()` returns true
when the alias displaced a rule that was already reachable (another registration
or a built-in).

**By instance** is the second form, covered next: it is only for a rule that needs
a dependency the Domain must not hold — never for passing parameters.

### Rules that need a dependency

A rule that must reach outside the object — a uniqueness check that queries the
database, say — cannot be registered as a class-string: the Validator builds those
itself, with no dependency to hand. Register such a rule as an **instance**
instead, from the Manager that already owns the DAO. The instance's alias is its
own `getErrorCode()`, which is the same name `validationRules()` references. This
mirrors the lazy-relation `setItemLoader()` pattern: the Domain declares the
alias, the Manager supplies the implementation once, from its constructor.

```php
use Closure;
use MiGears\Validator\RuleInterface;

final class UniqueEmailRule implements RuleInterface
{
    /** @param Closure(string): bool $emailExists */
    public function __construct(private Closure $emailExists) {}

    public function validate(mixed $value): bool
    {
        return $value === null || $value === ''
            || !($this->emailExists)((string) $value);
    }

    public function getErrorCode(): string { return 'uniqueEmail'; }
    public function getErrorParams(): array { return []; }
}

final class UserManager
{
    public function __construct(private UserDao $dao)
    {
        UserDomain::register(
            new UniqueEmailRule(fn (string $email) => $this->dao->existsByEmail($email))
        );
    }
}
```

`validationRules()` then references the alias like any other rule:

```php
'email' => ['required' => true, 'email' => true, 'uniqueEmail' => true],
```

The instance form carries a **dependency, not parameters**: the instance is already
built, so any config written for its alias in `validationRules()` is ignored.
Registering makes the alias *available*, it does not force it — the rule runs only
on fields whose `validationRules()` reference it, and a rule the declaration
disabled with `false` stays disabled. Re-registering an alias replaces the rule,
and registration is per class, so an injected rule never leaks into another Domain.

### Where the Validator lives

Each domain class keeps its own `Validator`, keyed by class name and reused
across calls. That per-class storage is load-bearing, not just a cache:

- **Scoping.** A rule registered on one class stays with that class — it never
  reaches a sibling, nor leaks from a parent into its children through the trait's
  shared storage.
- **Caching and persistence.** The Validator is built once per class, and it is
  what a registration is written into: a throwaway instance would lose every
  registered rule.

Two simpler shapes do not work:

- **One shared Validator.** Every class would see every other class's registered
  rules, and a parent's registrations would leak into its children.
- **An instance property holding it.** `toArray()` is `get_object_vars($this)`,
  so the stored Validator would be handed to the DAO as a column
  (`table users has no column named validator`) — the same trap the
  lazy-relation loader avoids by living in a static slot.

So the storage has to be static and per class — which is exactly what a trait
static property keyed by `static::class` provides.

### Error format

Errors use structured codes instead of hardcoded messages, ready for i18n:

```php
['field' => ['rule' => 'minLength', 'params' => ['min' => 3]]]
```

Pair with `migears/i18n` to translate:

```php
$message = $translator->translate(
    "validation.{$error['rule']}",
    ['field' => $fieldLabel, ...$error['params']]
);
```

Placeholders follow the `%name%` convention, so the template behind the example
above would read `Too short, at least %min%.`

## Why a Trait Instead of a Base Class?

1. **No inheritance constraint** — Domain classes can extend whatever they need
2. **Zero overhead** — trait methods are inlined into the class
3. **Maximum readability** — `DataAccess` is two methods, each a single line of logic

## License

MIT

---

# migears/domain

![Version](https://img.shields.io/badge/version-2.0.0-blue)

极简 Domain 层 — 纯数据容器，零映射。

## 设计哲学

- **不用 Getter/Setter** — 属性全部 `public readonly`
- **不用 Hydrator/Mapper** — 直接 `new XxxDomain(...$row)` 构造
- **不用基类继承** — 使用 `DataAccess` trait
- **字段名与数据库列名完全一致** — 不做驼峰/下划线互转
- **不含持久化逻辑** — Domain 不知道 SQL 和 DAO 的存在

## 边界

**范围内**

- `DataAccess` trait：`fromArray()`（数组 → Domain）与 `toArray()`（Domain → 数组），列名与属性名 1:1 绑定、不做驼峰转换；PSR-4 根为 `MiGears\Domain`。
- `Validatable` trait：按类声明规则（`validationRules()`）、`validate()` / `isValid()` / `validateArray()` / `isValidArray()`，以及按类的 `register()` 用于注册自定义规则（类名或注入的实例）。
- Domain 对象作为纯 `public readonly` 数据载体，以及推荐的静态懒加载关联访问器模式（`setItemLoader()`），让 Domain 不持有 DAO/Manager 引用。

**范围外（刻意不做）**

- 持久化：Domain 不知道 SQL 和 DAO 的存在 —— 生成语句属于 `migears/sql`，执行语句与转换结果行属于 `migears/dao`。
- 验证规则集合本身：`Validatable` 只声明规则并委托给共享的 `Validator`；内置验证器与规则执行属于 `migears/validator`，把返回的错误码翻译成文案属于 `migears/i18n`。
- Hydration、映射与标量转换：本包没有 hydrator/mapper，也没有强制转换层；PDO（PHP 8.1+）已给出原生 `int`/`float`/`string`/`null`，`fromArray()` 只按参数名绑定。
- 懒加载与注入式验证规则的 wiring：在 Manager 构造函数里调用 `setItemLoader()` / `register()` 是 `migears/manager` 的职责 —— 这里没有生命周期钩子。

## 安装

```bash
composer require migears/domain
```

要求：PHP 8.1+、`migears/validator`。

## 快速开始

### 定义 Domain

```php
use MiGears\Domain\DataAccess;

class UserDomain
{
    use DataAccess;

    public function __construct(
        public readonly int $id,
        public readonly string $user_name,
        public readonly string $email,
        public readonly int $age,
        public readonly string $created_at,
    ) {}
}
```

### 数组 → Domain

```php
// 从数据库行构造
$row = ['id' => 1, 'user_name' => 'Alice', 'email' => 'a@b.com', 'age' => 25, 'created_at' => '2024-01-01'];

$user = UserDomain::fromArray($row);
echo $user->user_name;  // "Alice"
```

`fromArray()` 通过 `...$row` 展开关联数组，利用 PHP 8.x 命名参数特性，数组键名必须与构造函数参数名完全匹配。

### Domain → 数组

```php
$row = $user->toArray();
// ['id' => 1, 'user_name' => 'Alice', 'email' => 'a@b.com', 'age' => 25, 'created_at' => '2024-01-01']
```

`toArray()` 使用 `get_object_vars($this)`，返回所有属性组成的关联数组。

### 往返转换

```php
$domain = UserDomain::fromArray($row);
$back = $domain->toArray();
// $back === $row  ✅  （键序一致时）
```

`===` 还要求键序完全一致：`get_object_vars()` 按属性声明序返回，因此当 `SELECT *` 的
列序与构造参数序不同时，`===` 为 false 而 `==` 仍为 true。可在查询中显式列出列序，
或改用 `==` 比较。

## 类型契约

`fromArray()` 通过 PHP 8.x **强类型命名参数**绑定值，因此这里**不做任何类型转换**。每个值必须已经是构造参数所声明的类型：

- `int $id` 参数需要真正的 PHP `int`，而不是字符串 `'1'`
- 缺少键、多出键或类型不匹配都会抛出原生 `\Error` / `\TypeError`

原生类型**并不是** Domain（或 DAO）需要产出的东西 — PDO 已经给出了。PHP 8.1
起，结果集对数字列即返回真正的 PHP `int` / `float`，模拟预处理与原生预处理
皆然，各内置驱动一致。因此取值链路是：

```
PDO（PHP 8.1+）→ 原生 PHP 类型（int、float、string、null）
             ↓
  Domain::fromArray() 仅按参数名绑定（不 hydration、不强制转换）
```

这正是为什么 Domain 与 DAO 都不需要 hydrator 和标量转换逻辑。

有三点需要注意：

- `DECIMAL` 列保持 `string`（为保留精度）— 请声明为 `string`
- `TINYINT(1)` 列是 `int` — 请声明为 `int`，而非 `bool`
- 切勿开启 `PDO::ATTR_STRINGIFY_FETCHES`：它会恢复「一切皆字符串」的旧行为，
  使每个 `int` 属性都报错

构造参数类型与列类型不一致时会**大声失败**（原生 `\TypeError`），而不是静默
转换 — 这是刻意设计：当场暴露 schema 漂移，而非把 `'2024-01-01'` 强转成 `2024`。

## 命名规范

| 层级 | 规范 | 示例 |
|------|------|------|
| 数据库列名 | 下划线 | `user_name` |
| Domain 属性 | 下划线（与列名一致） | `$user_name` |
| 构造函数参数 | 下划线（与列名一致） | `string $user_name` |

全程不做驼峰/下划线互转。数据库里是什么，代码里就是什么。

## 架构

Domain 是 miGears 三层数据架构的中间层：

```
Service 层（业务逻辑）
    ↓ 调用
DAO 层（接收/返回 Domain 对象）→ migears/dao
    ↓ 内部调用
SQL 层（SQL + 参数 → 数组）→ migears/sql
    ↓
PDO / MySQL
```

- Domain 不知道 SQL 和 DAO 的存在
- DAO 用 `fromArray()` 把 SQL 结果转为 Domain 对象
- DAO 用 `toArray()` 把 Domain 对象转回数组供 SQL 使用

## 关联数据（懒加载）

Domain 对象保持纯净：不持有 DAO、Manager 或任何其他模块引用。当业务代码仍希望写成
`$order->items()` 时，推荐的做法是**在构造之前注入静态 loader**——Domain 只声明访问器，
实现由 Manager 提供。依赖知识因此全部留在 Manager 中，Domain 依旧可独立测试。

### Manager 侧

```php
final class OrderManager
{
    public function __construct(private OrderItemDao $itemDao)
    {
        OrderDomain::setItemLoader(
            fn(OrderDomain $order) => $this->itemDao->getByOrderId($order->id)
        );
    }
}
```

这里没有生命周期钩子，也没有任何东西会替你调用 `boot()`：wiring 在对外提供服务之前
把每个 Manager 建一次，所以构造函数就是它该在的地方（见 `migears/manager`）。

### Domain 侧

```php
use Closure;
use MiGears\Domain\DataAccess;
use RuntimeException;

class OrderDomain
{
    use DataAccess;

    /** @var null|Closure(self): list<OrderItemDomain> */
    private static ?Closure $itemLoader = null;

    public function __construct(
        public readonly int $id,
        public readonly string $title,
    ) {}

    public static function setItemLoader(?callable $loader): void
    {
        static::$itemLoader = $loader === null ? null : Closure::fromCallable($loader);
    }

    /** @return list<OrderItemDomain> */
    public function items(): array
    {
        if (static::$itemLoader === null) {
            throw new RuntimeException('OrderDomain::setItemLoader() was not called');
        }

        return (static::$itemLoader)($this);
    }
}
```

```php
$order = OrderDomain::fromArray($row);
$order->items();     // 经 Manager 注入的 callable 加载
$order->toArray();   // ['id' => ..., 'title' => ...] —— 不含 loader
```

### 为什么不把 loader 存在实例上

因为任何实例属性都会污染持久化。`toArray()` 的实现是 `get_object_vars($this)`，
所以存下来的 callable——**即便是 `private` 的**——会进入 DAO 交给 SQL 的数组：

```
toArray() → ['id' => 7, 'title' => 'Order A', 'itemsLoader' => Closure]
SQL       → ERROR: table orders has no column named itemsLoader
```

静态存储则让 `fromArray()` 和 `toArray()` 完全不受影响。

### 约定

- 成员声明为 `?Closure`。属性类型写 `callable` 会**致命错误**（`Property ... cannot have
  type callable`）；setter 收 `callable`，用 `Closure::fromCallable()` 归一化。
- setter 收 `?callable`，把 `null` 视为复位。静态状态会跨测试存活，因此 `tearDown()`
  应调用 `setItemLoader(null)`。
- loader 从未注入时应当抛异常。静默返回 `[]` 会让「没有关联数据」与「忘了配置」无法区分。
- 用 `static::` 而非 `self::`，让访问器保持可覆盖。
- 未自行声明槽位的子类会继承父类的 loader；若需独立的 loader，子类必须自己声明槽位与 setter。
- 这是刻意的全局状态，也是本包唯一推荐使用它的地方。请只在一处注入：Manager 的构造函数。

### 适用场景

适用于不想预加载、也不想让 Domain 知道如何取数的关联记录与子集合。纯字段读取不必使用——
它们本就在对象上；而如果不同调用方需要同一类的不同 loader，则说明应由调用方去问 Manager。

## Validatable 自验证

Domain 对象可以使用 `Validatable` trait 自验证数据。验证规则定义在 domain 类自身，错误以结构化的错误码 + 参数形式返回（i18n 就绪）。

依赖 `migears/validator`。

`Validatable` 把 `toArray(): array` 声明为 abstract，因为 `validate()` 与 `isValid()`
通过它读取实例状态。因此使用 `Validatable` 的类必须提供 `toArray()` —— 或用 `DataAccess`
（见下例），或自行实现。

```php
use MiGears\Domain\DataAccess;
use MiGears\Domain\Validatable;

class UserDomain
{
    use DataAccess;
    use Validatable;

    public function __construct(
        public readonly string $username,
        public readonly string $email,
        public readonly int $age = 0,
    ) {}

    protected static function validationRules(): array
    {
        return [
            'username' => ['required' => true, 'minLength' => 3, 'maxLength' => 20],
            'email'    => ['required' => true, 'email' => true],
            'age'      => ['integer' => true, 'min' => 0, 'max' => 150],
        ];
    }
}
```

### 验证实例

```php
$user = new UserDomain('ab', 'invalid', -1);

$errors = $user->validate();
// [
//   'username' => ['rule' => 'minLength', 'params' => ['min' => 3]],
//   'email'    => ['rule' => 'email', 'params' => []],
//   'age'      => ['rule' => 'min', 'params' => ['min' => 0]],
// ]

$user->isValid(); // false
```

### 构造前验证

`$_POST` 这类原始输入全是字符串，而 `fromArray()` 拒绝类型转换（见「类型契约」），
因此它无法构造声明了非字符串字段的 domain —— `int $age` 遇到 `'25'` 会抛 `TypeError`。
先校验数组，再构造：

```php
$errors = UserDomain::validateArray($_POST);

if ($errors === []) {
    $user = UserDomain::fromArray($_POST);
}
```

### 自定义验证规则

自定义规则用 `register()` 注册，有两种形态。

**传类名 —— 常规形态。** 别名由类名推导，引擎依据 `validationRules()` 里该字段的配置来构造规则。
因此规则的**参数写在规则表里**，不在注册处：

```php
use MiGears\Validator\RuleInterface;

final class StrengthRule implements RuleInterface
{
    public function __construct(private int $min = 8) {}
    public function validate(mixed $value): bool
    {
        return is_string($value) && strlen($value) >= $this->min;
    }
    public function getErrorCode(): string { return 'strength'; }
    public function getErrorParams(): array { return ['min' => $this->min]; }
}

UserDomain::register(StrengthRule::class);

// validationRules() 里：
'password' => ['strength' => 12],   // → new StrengthRule(12)
'pin'      => ['strength' => 4],    // → new StrengthRule(4)
```

同一个类，按字段给不同参数。注册按类隔离，在一个 domain 类上注册的规则不会泄漏到另一个类。
当别名挤掉了已可达的规则（另一次注册或某个内置规则）时，`register()` 返回 true。

**传实例**是第二种形态，见下一节：它只用于「规则需要 Domain 不该持有的依赖」，绝不用于传参。

### 需要依赖的规则

有些规则必须访问对象之外的东西 —— 例如查库判断唯一性 —— 它们不能按类名注册：那些类由
Validator 自己构造，拿不到任何依赖。这类规则应以**实例**形式注册，由已经持有 DAO 的
Manager 来做。实例的别名取自它自己的 `getErrorCode()`，正是 `validationRules()` 引用的那个
名字。这与关联数据的 `setItemLoader()` 范式一致：Domain 只声明别名，Manager 在构造函数里
一次性提供实现。

```php
use Closure;
use MiGears\Validator\RuleInterface;

final class UniqueEmailRule implements RuleInterface
{
    /** @param Closure(string): bool $emailExists */
    public function __construct(private Closure $emailExists) {}

    public function validate(mixed $value): bool
    {
        return $value === null || $value === ''
            || !($this->emailExists)((string) $value);
    }

    public function getErrorCode(): string { return 'uniqueEmail'; }
    public function getErrorParams(): array { return []; }
}

final class UserManager
{
    public function __construct(private UserDao $dao)
    {
        UserDomain::register(
            new UniqueEmailRule(fn (string $email) => $this->dao->existsByEmail($email))
        );
    }
}
```

之后 `validationRules()` 像引用普通规则一样引用该别名：

```php
'email' => ['required' => true, 'email' => true, 'uniqueEmail' => true],
```

实例形态携带的是**依赖，不是参数**：实例已经构造完成，所以 `validationRules()` 里为它写的配置会被忽略。
注册只是让别名**可用**，不是强制启用 —— 只有 `validationRules()` 引用了它的字段才会执行，
被声明用 `false` 关闭的规则保持关闭。对同一别名重复注册会替换规则，注册按类隔离，
注入的规则不会泄漏到其它 Domain。

### Validator 存在哪里

每个 domain 类各持一个 `Validator`，以类名为键、跨调用复用。这个按类存储是承重的，
不只是缓存：

- **作用域。** 一个类注册的规则只属于该类 —— 不会到达兄弟类，也不会经由 trait 的共享存储
  从父类泄漏到子类。
- **缓存与持久化。** Validator 每类只构造一次，而注册正是写进它里面：换成用完即丢的实例
  会丢掉每一条注册的规则。

两种更简单的形态都不成立：

- **单一共享 Validator。** 每个类都会看见其它所有类注册的规则，父类的注册也会泄漏到子类。
- **用实例属性保存它。** `toArray()` 是 `get_object_vars($this)`，存下来的 Validator 会被
  当作一列交给 DAO（`table users has no column named validator`）—— 与懒加载 loader 靠
  静态槽位规避的是同一个坑。

所以存储必须既是静态、又按类 —— 而以 `static::class` 为键的 trait 静态属性正好如此。

### 错误格式

错误使用结构化代码而非硬编码消息，i18n 就绪：

```php
['字段名' => ['rule' => 'minLength', 'params' => ['min' => 3]]]
```

配合 `migears/i18n` 翻译：

```php
$message = $translator->translate(
    "validation.{$error['rule']}",
    ['field' => $fieldLabel, ...$error['params']]
);
```

占位符遵循 `migears/i18n` 的 `%name%` 约定，例如上面示例对应的消息模板可写作
`太短了，至少 %min% 个字符`。

## 为什么用 Trait 而不是基类？

1. **不受继承约束** — Domain 类可以继承任何需要的父类
2. **零开销** — trait 方法会被内联到类中
3. **最大可读性** — `DataAccess` 只有两个方法，各一行逻辑

## 许可证

MIT
