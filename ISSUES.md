# migears-domain — Known Issues

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> From the miGears Full-Module Code Review Report (5th round, 2026-09-28).

| | |
|---|---|
| Status | **Best state** |
| Size | src 55 lines (net) · 34 tests · 2 src files |

Legend — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs

## At a glance

| | |
|---|---|
| Unsettled | P0 0 · P1 0 · P2 1 · P3 2 · other 0 |
| Settled | 0 of 3 |
| Waiting on the owner | `P2-1`, `P3-1`, `P3-2` |
| Waiting on the reviewer | _nothing_ |
| Waiting on the coordinator | _nothing_ |
| Deferred, owing nobody | _nothing_ |

| id | level | status | title |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **open** | `Validatable::validate()` calls `$this->toArray()`, which comes from … |
| [`P3-1`](issues/P3-1.md) | P3 | **open** | The README describes `DataAccess` as 'about 15 lines' while the file is … |
| [`P3-2`](issues/P3-2.md) | P3 | **open** | Two comments still describe the opposite behaviour: … |

## Unclosed

What is left to do here: every item whose `status` is not `verified` or `closed`,
highest severity first. `waiting on` is the party who acts next, read from that status.

| | |
|---|---|
| Unclosed | **3** of 3 |
| By status | `open` 3 |
| Waiting on | owner 3 |

| level | item | status | waiting on | title |
|---|---|---|---|---|
| **P2** | [`P2-1`](issues/P2-1.md) | `open` | owner | `Validatable::validate()` calls `$this->toArray()`, which comes from … |
| **P3** | [`P3-1`](issues/P3-1.md) | `open` | owner | The README describes `DataAccess` as 'about 15 lines' while the file is … |
| **P3** | [`P3-2`](issues/P3-2.md) | `open` | owner | Two comments still describe the opposite behaviour: … |

## Verdict

Two tiny traits — DataAccess and Validatable — that do exactly what they promise with zero magic. The compile-time toArray() contract is a deliberate break documented in the closure audit.

## Fixed since the last round

Both prior items confirmed fixed: P2-1 Validatable now declares abstract public function toArray() (compile-time break, intentional); P3-1/P3-2 metadata drift resolved.

## Test gaps

No test for Validatable with a validator that returns a non-string, non-array error shape; no test for DataAccess with a null/empty idColumn.

## Verification protocol

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.


---

# migears-domain — 已知问题

> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> 出自 miGears 全模块代码评审报告（5th round，2026-09-28）。

| | |
|---|---|
| 状态 | **状态最好** |
| 体量 | src 55 行（净）· 34 个用例 · 2 个源文件 |

级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## 状态一览

| | |
|---|---|
| 未了结 | P0 0 · P1 0 · P2 1 · P3 2 · 其他 0 |
| 已了结 | 0 / 3 |
| 等负责人 | `P2-1`, `P3-1`, `P3-2` |
| 等评审方 | _无_ |
| 等协调人 | _无_ |
| 已暂缓，不欠谁 | _无_ |

| id | 级别 | 状态 | 标题 |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **open** | Validatable::validate() 调用 $this->toArray()，它来自 DataAccess，但 trait 只把 … |
| [`P3-1`](issues/P3-1.md) | P3 | **open** | README 称 DataAccess「约 15 行」，而该文件 62 行（净代码约 15 行）——措辞已加限定，但仍易被读成文件总行数。 |
| [`P3-2`](issues/P3-2.md) | P3 | **open** | 两处注释仍与行为相反：tests/ParentValidatableUser.php:11 与 … |

## 未关闭

本模块还剩什么要做：所有 `status` 不是 `verified` 或 `closed` 的条目，按严重度从高到低。
`waiting on` 是下一步该动手的一方，由其状态读出。

| | |
|---|---|
| 未关闭 | **3** / 3 |
| 按状态 | `open` 3 |
| 等在谁 | 负责人 3 |

| 级别 | 条目 | 状态 | 等在谁 | 标题 |
|---|---|---|---|---|
| **P2** | [`P2-1`](issues/P2-1.md) | `open` | 负责人 | Validatable::validate() 调用 $this->toArray()，它来自 DataAccess，但 trait 只把 … |
| **P3** | [`P3-1`](issues/P3-1.md) | `open` | 负责人 | README 称 DataAccess「约 15 行」，而该文件 62 行（净代码约 15 行）——措辞已加限定，但仍易被读成文件总行数。 |
| **P3** | [`P3-2`](issues/P3-2.md) | `open` | 负责人 | 两处注释仍与行为相反：tests/ParentValidatableUser.php:11 与 … |

## 结论

两个极简 trait——DataAccess 与 Validatable——严格兑现承诺，零魔法。编译期 toArray() 契约是有意的破坏性变更，已在结案审计中记录。

## 本轮已修复确认

Both prior items confirmed fixed: P2-1 Validatable now declares abstract public function toArray() (compile-time break, intentional); P3-1/P3-2 metadata drift resolved.

## 测试盲区

无 Validatable 搭配返回非字符串/非数组错误形态验证器的测试；无 DataAccess 使用 null/空 idColumn 的测试。

## 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- `phpunit.xml.dist` 中的 warning/notice/deprecation/risky 开关：四个全开
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
