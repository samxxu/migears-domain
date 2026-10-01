# migears-domain — Known Issues

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> From the miGears Full-Module Code Review Report (6th round, 2026-10-01).

| | |
|---|---|
| Status | **Best state** |
| Size | src 55 lines (net) · 34 tests · 2 src files |

Legend — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs

## At a glance

| | |
|---|---|
| Unsettled | P0 0 · P1 0 · P2 0 · P3 1 · other 0 |
| Settled | 3 of 4 |
| Waiting on the owner | _nothing_ |
| Waiting on the coordinator | _nothing_ |
| Waiting on the reviewer | `P3-3` |
| Deferred, owing nobody | _nothing_ |

| id | level | status | title |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **verified** | `Validatable::validate()` calls `$this->toArray()`, which comes from … |
| [`P3-1`](issues/P3-1.md) | P3 | **verified** | The README describes `DataAccess` as 'about 15 lines' while the file is … |
| [`P3-2`](issues/P3-2.md) | P3 | **verified** | Two comments still describe the opposite behaviour: … |
| [`P3-3`](issues/P3-3.md) | P3 | **fixed** | The README says twice that fromArray() 'binds positionally', while … |

## Unclosed

What is left to do here: every item whose `status` is not `verified` or `closed`,
highest severity first. `waiting on` is the party who acts next, read from that status.

| | |
|---|---|
| Unclosed | **1** of 4 |
| By status | `fixed` 1 |
| Waiting on | reviewer 1 |

| level | item | status | waiting on | title |
|---|---|---|---|---|
| **P3** | [`P3-3`](issues/P3-3.md) | `fixed` | reviewer | The README says twice that fromArray() 'binds positionally', while … |

## Verdict

The compile-time contract and its migration note are both in place; one internal README contradiction about how fromArray() binds was found.

## Fixed since the last round

P2-1 verified by mutation: Validatable declares abstract toArray() at the type level, and both README halves carry the migration note the 2026-09-29 ruling asked for. Deleting the declaration turns the module’s own test red with a ReflectionException.

## Test gaps

DataAccess::toArray() has no test for a class with private properties; Validatable::getValidator()’s per-class cache has no reverse case where a subclass initialises before its parent.

## Verification protocol

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.


---

# migears-domain — 已知问题

> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> 出自 miGears 全模块代码评审报告（6th round，2026-10-01）。

| | |
|---|---|
| 状态 | **状态最好** |
| 体量 | src 55 行（净）· 34 个用例 · 2 个源文件 |

级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## 状态一览

| | |
|---|---|
| 未了结 | P0 0 · P1 0 · P2 0 · P3 1 · 其他 0 |
| 已了结 | 3 / 4 |
| 等模块主 | _无_ |
| 等协调人 | _无_ |
| 等评审方 | `P3-3` |
| 已暂缓，不欠谁 | _无_ |

| id | 级别 | 状态 | 标题 |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **verified** | Validatable::validate() 调用 $this->toArray()，它来自 DataAccess，但 trait 只把 … |
| [`P3-1`](issues/P3-1.md) | P3 | **verified** | README 称 DataAccess「约 15 行」，而该文件 62 行（净代码约 15 行）——措辞已加限定，但仍易被读成文件总行数。 |
| [`P3-2`](issues/P3-2.md) | P3 | **verified** | 两处注释仍与行为相反：tests/ParentValidatableUser.php:11 与 … |
| [`P3-3`](issues/P3-3.md) | P3 | **fixed** | README 两处称 fromArray()「按位置绑定」，而 README 另一节、trait 自己的 docblock 与用例都表明是 … |

## 未关闭

本模块还剩什么要做：所有 `status` 不是 `verified` 或 `closed` 的条目，按严重度从高到低。
`waiting on` 是下一步该动手的一方，由其状态读出。

| | |
|---|---|
| 未关闭 | **1** / 4 |
| 按状态 | `fixed` 1 |
| 等在谁 | 评审方 1 |

| 级别 | 条目 | 状态 | 等在谁 | 标题 |
|---|---|---|---|---|
| **P3** | [`P3-3`](issues/P3-3.md) | `fixed` | 评审方 | README 两处称 fromArray()「按位置绑定」，而 README 另一节、trait 自己的 docblock 与用例都表明是 … |

## 结论

编译期契约与迁移说明都已落地；另发现 README 内部一处关于 fromArray() 绑定方式的自相矛盾。

## 本轮已修复确认

P2-1 verified by mutation: Validatable declares abstract toArray() at the type level, and both README halves carry the migration note the 2026-09-29 ruling asked for. Deleting the declaration turns the module’s own test red with a ReflectionException.

## 测试盲区

DataAccess::toArray() 对含私有属性的类无用例；Validatable::getValidator() 的按类缓存无「子类先于父类初始化」的反向用例。

## 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- `phpunit.xml.dist` 中的 warning/notice/deprecation/risky 开关：四个全开
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
