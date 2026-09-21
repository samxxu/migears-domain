# migears/domain

![Version](https://img.shields.io/badge/version-2.0.0-blue)

Minimalist Domain layer — pure data containers with zero mapping.

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
