# migears-domain — Known Issues / 已知问题

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> From the miGears Full-Module Code Review Report (4th round, 2026-09-27).

| | |
|---|---|
| Status / 状态 | **Best state / 状态最好** |
| Size / 体量 | src 216 lines (54 net) · 32 tests · 2 src files |

Legend / 图例 — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs
级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## At a glance / 状态一览

| | |
|---|---|
| Items / 条目 | P0 0 · P1 0 · P2 1 · P3 2 · other 0 |
| Answered / 已回复 | 0 of 3 |
| Waiting / 等待回复 | `P2-1`, `P3-1`, `P3-2` |

| id | level | status | title |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **open** | `Validatable::validate()` calls `$this->toArray()`, which comes from … |
| [`P3-1`](issues/P3-1.md) | P3 | **open** | The README describes `DataAccess` as 'about 15 lines' while the file is … |
| [`P3-2`](issues/P3-2.md) | P3 | **open** | Two comments still describe the opposite behaviour: … |

## Verdict / 结论

The smallest module in the workspace (54 net lines of source) and it behaves like it. One real gap remains: `validate()` silently depends on `toArray()` from a sibling trait that the trait itself never requires.

全仓最薄的模块（净源码 54 行），行为也如其所是。剩一处实质缺口：validate() 隐式依赖兄弟 trait 的 toArray()，而自身从未声明该契约。

## Fixed since the last round / 本轮已修复确认

上一轮 7 项中 5 项修复：README 改回 translate()、往返断言写出键序前提、composer 描述去掉 array access、validator 依赖去掉 @dev、README 行数口径收敛。 

## Test gaps / 测试盲区

No test for "uses Validatable without DataAccess" (the fixtures that use Validatable alone only call the static `validateArray()`, dodging the path); no test pinning the key-order dependence of the round-trip assertion.

无「只用 Validatable 不用 DataAccess」的用例（单独使用 Validatable 的夹具只调静态 validateArray()，恰好绕开该路径）；无固化往返断言键序依赖的用例。

## Verification protocol / 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
