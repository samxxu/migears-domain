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

## 为什么用 Trait 而不是基类？

1. **不受继承约束** — Domain 类可以继承任何需要的父类
2. **零开销** — trait 方法会被内联到类中
3. **最大可读性** — 两个方法，总共约 15 行代码

## 许可证

MIT
