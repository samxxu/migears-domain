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

## Installation

```bash
composer require migears/domain
```

Requires: PHP 8.1+.

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
// $back === $row  ✅
```

## Type Contract

`fromArray()` binds values via PHP 8.x **strict typed named arguments**, so no type coercion happens here. Every value must already be of the type declared by the constructor parameter:

- an `int $id` parameter requires a genuine PHP `int`, not the string `'1'`
- a missing key, an extra key, or a type mismatch throws a native `\Error` / `\TypeError`

The responsibility for native typing sits with the **data source layer** (DAO / SQL), not the Domain:

```
SQL / DAO guarantees → native PHP types (int, string, bool, float, nullable)
                   ↓
  Domain::fromArray() only binds them positionally (no hydration, no casting)
```

This is why the Domain layer stays free of hydrators and scalar-conversion logic.

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
    public function __construct(private OrderItemDao $itemDao) {}

    public function boot(): void
    {
        OrderDomain::setItemLoader(
            fn(OrderDomain $order) => $this->itemDao->getByOrderId($order->id)
        );
    }
}
```

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
  Inject once, from a single bootstrap or Manager location.

### When to use it

Use it for related records and child collections that you do not want to load
eagerly and do not want the Domain to know how to fetch. Do not use it for plain
column reads — those already live on the object — and do not use it when callers
need different loaders for the same class; that is a sign the caller should ask
the Manager instead.

## Self-Validation with Validatable

Domain objects can validate their own data using the `Validatable` trait. Validation rules are defined in the domain class itself, and errors are returned as structured error codes + params (i18n-ready).

Depends on `migears/validator`.

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

```php
$errors = UserDomain::validateArray($_POST);

if ($errors === []) {
    $user = UserDomain::fromArray($_POST);
}
```

### Custom validation rules

Rules that are not part of the built-in set can be added by overriding `customValidators()`. Each entry is a validator class-string (the rule alias is derived from the class name); the domain class's shared validator is pre-registered with these on first use, scoped to that class only.

```php
use MiGears\Validator\ValidatorInterface;

final class StrongPasswordValidator implements ValidatorInterface
{
    public function validate(mixed $value): bool { /* ... */ }
    public function getErrorCode(): string { return 'strongPassword'; }
    public function getErrorParams(): array { return []; }
}

class UserDomain
{
    use DataAccess;
    use Validatable;

    // ...constructor & validationRules()...

    protected static function customValidators(): array
    {
        return [StrongPasswordValidator::class];
        // `strongPassword` is now available in validationRules()
    }
}
```

Custom rules registered for one domain class never leak into others.

### Error format

Errors use structured codes instead of hardcoded messages, ready for i18n:

```php
['field' => ['rule' => 'minLength', 'params' => ['min' => 3]]]
```

Pair with `migears/i18n` to translate:

```php
$message = $translator->get(
    "validation.{$error['rule']}",
    ['field' => $fieldLabel, ...$error['params']]
);
```

## Why a Trait Instead of a Base Class?

1. **No inheritance constraint** — Domain classes can extend whatever they need
2. **Zero overhead** — trait methods are inlined into the class
3. **Maximum readability** — two methods, total ~15 lines of code

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

## 安装

```bash
composer require migears/domain
```

要求：PHP 8.1+。

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
// $back === $row  ✅
```

## 类型契约

`fromArray()` 通过 PHP 8.x **强类型命名参数**绑定值，因此这里**不做任何类型转换**。每个值必须已经是构造参数所声明的类型：

- `int $id` 参数需要真正的 PHP `int`，而不是字符串 `'1'`
- 缺少键、多出键或类型不匹配都会抛出原生 `\Error` / `\TypeError`

原生类型保证的责任在**数据源层**（DAO / SQL），而非 Domain 层：

```
SQL / DAO 保证 → 原生 PHP 类型（int、string、bool、float、nullable）
             ↓
  Domain::fromArray() 仅按位绑定（不 hydration、不强制转换）
```

这正是为什么 Domain 层不需要 hydrator 和标量转换逻辑。

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
    public function __construct(private OrderItemDao $itemDao) {}

    public function boot(): void
    {
        OrderDomain::setItemLoader(
            fn(OrderDomain $order) => $this->itemDao->getByOrderId($order->id)
        );
    }
}
```

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
- 这是刻意的全局状态，也是本包唯一推荐使用它的地方。请只在一处 bootstrap 或 Manager 中注入。

### 适用场景

适用于不想预加载、也不想让 Domain 知道如何取数的关联记录与子集合。纯字段读取不必使用——
它们本就在对象上；而如果不同调用方需要同一类的不同 loader，则说明应由调用方去问 Manager。

## Validatable 自验证

Domain 对象可以使用 `Validatable` trait 自验证数据。验证规则定义在 domain 类自身，错误以结构化的错误码 + 参数形式返回（i18n 就绪）。

依赖 `migears/validator`。

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

```php
$errors = UserDomain::validateArray($_POST);

if ($errors === []) {
    $user = UserDomain::fromArray($_POST);
}
```

### 自定义验证规则

不在内置集合里的规则，可通过覆盖 `customValidators()` 添加。每个条目是一个验证器类名（规则别名由类名推导）；domain 类在首次使用时把自定义规则预注册到共享的验证器实例上，且仅作用于本类。

```php
use MiGears\Validator\ValidatorInterface;

final class StrongPasswordValidator implements ValidatorInterface
{
    public function validate(mixed $value): bool { /* ... */ }
    public function getErrorCode(): string { return 'strongPassword'; }
    public function getErrorParams(): array { return []; }
}

class UserDomain
{
    use DataAccess;
    use Validatable;

    // ...构造器与 validationRules()...

    protected static function customValidators(): array
    {
        return [StrongPasswordValidator::class];
        // `strongPassword` 现在可以在 validationRules() 中使用
    }
}
```

为一个 domain 类注册的自定义规则不会泄漏到其它类。

### 错误格式

错误使用结构化代码而非硬编码消息，i18n 就绪：

```php
['字段名' => ['rule' => 'minLength', 'params' => ['min' => 3]]]
```

配合 `migears/i18n` 翻译：

```php
$message = $translator->get(
    "validation.{$error['rule']}",
    ['field' => $fieldLabel, ...$error['params']]
);
```

## 为什么用 Trait 而不是基类？

1. **不受继承约束** — Domain 类可以继承任何需要的父类
2. **零开销** — trait 方法会被内联到类中
3. **最大可读性** — 两个方法，总共约 15 行代码

## 许可证

MIT
