---
title: 数据库迁移列必须携带简体中文注释
date: 2026-11-15
category: conventions
module: 数据库迁移
problem_type: convention
component: database
severity: medium
applies_when:
  - "新增或修改 Laravel 数据库迁移时"
  - "为已有数据库补充列注释时"
  - "使用 timestamps() 或 morphs() 等一次生成多列的快捷方法时"
  - "修改已经执行过的迁移文件时"
tags: [migration, column-comment, laravel, schema, chinese-comment]
---

# 为所有迁移列补齐简体中文注释

## Context

本项目的建库迁移长期只声明列名和类型，没有列注释。数据库使用 MySQL（`DB_CONNECTION=mysql`），`mes_products.status`、`sys_users.status`、`jobs.created_at` 这类列名脱离上下文后语义不清：同一个 `status` 在不同表里可能是启用状态、任务状态或版本状态；`jobs.created_at` 甚至是 `unsignedInteger` 而不是时间类型。

结果是只能回到迁移文件反查列的含义，或者直接读业务代码猜。9 个迁移（含 Laravel 默认的 `users`/`cache`/`jobs`，以及 `personal_access_tokens`、RBAC、组织、产品表）因此统一补齐了 `->comment('...')`。

## Guidance

1. **每个列都要有简体中文注释。** 新建表、给已有表加列都适用，注释紧跟在完整列定义链的末尾：

   ```php
   $table->foreignId('product_id')->comment('产品 ID')->constrained('mes_products')->restrictOnDelete();
   ```

2. **`->comment()` 是列修饰符，与 `nullable()`、`default()`、`index()` 的顺序无关，但必须写在外键的 `constrained()` 之前。** `constrained()` 返回的是外键定义对象，链在它后面的 `comment()` 会写到外键上而不是列上，注释会静默丢失。

3. **一次创建多列的快捷方法不能逐列加注释，拆成单列。** `timestamps()` 返回两列的集合，`morphs()`/`nullableMorphs()` 返回 `void`，都没有单列可链式调用 `comment()`：

   ```php
   $table->timestamp('created_at')->nullable()->comment('创建时间');
   $table->timestamp('updated_at')->nullable()->comment('更新时间');
   ```

4. **拆快捷方法时保持原语义不变。** `morphs('tokenable')` 同时创建 `tokenable_type`、`tokenable_id` 和复合索引，拆开后必须手工补回索引：

   ```php
   $table->string('tokenable_type')->comment('令牌主体类型');
   $table->unsignedBigInteger('tokenable_id')->comment('令牌主体 ID');

   $table->index(['tokenable_type', 'tokenable_id']);
   ```

5. **区分“改迁移”和“改数据库”。** 修改已执行过的迁移只影响 `migrate:fresh` 的全新安装；现有库不会因此获得注释。若现有库也必须补注释，另写一个回填迁移，逐列 `->change()->comment('...')`，而不是重写历史迁移。

   ```php
   Schema::table('sys_users', function (Blueprint $table) {
       $table->string('name')->comment('姓名')->change();
   });
   ```

## Why This Matters

- 注释落在 `information_schema.COLUMNS.COLUMN_COMMENT`，`SHOW FULL COLUMNS`、数据库客户端和 IDE 都能直接读到，排查线上问题时不必先找迁移文件。
- 注释表达的是业务语义，不是类型复述。`jobs.created_at` 注释为“创建时间”，纠正了“整数时间戳”的歧义；`sys_users.status` 注释为“状态”而不是“是否启用”，避免按字面猜测布尔含义。
- 拆 `timestamps()`/`morphs()` 是唯一的代价：多写几行，但换来一致的注释覆盖，且不改变列类型、可空性和索引。
- 不遵守第 5 条会造成“迁移文件看起来有注释、实际库没有”的假象，这类漂移比完全没有注释更难发现。

> 当前仓库状态：9 个迁移文件已全部补齐注释，但没有提交回填迁移，现有开发库需要 `migrate:fresh` 或后续回填才会出现注释。

## When to Apply

- 新增任何 `Schema::create` 迁移时。
- 用 `Schema::table` 给已有表加列时（`after()` 等位置修饰符之后再写 `comment()`）。
- 用到 `timestamps()`、`morphs()`、`nullableMorphs()` 等一次性生成多列的快捷方法时；`softDeletes()`/`rememberToken()` 返回单列，可直接链 `comment()`。
- 已有数据库必须获得注释，而迁移早已执行过时——此时写回填迁移，不改历史文件。

## Examples

**基础列：`users` 表**

```php
// 之前
$table->id();
$table->string('name');
$table->string('email')->unique();
$table->timestamp('email_verified_at')->nullable();
$table->timestamps();

// 之后
$table->id()->comment('主键');
$table->string('name')->comment('姓名');
$table->string('email')->unique()->comment('邮箱');
$table->timestamp('email_verified_at')->nullable()->comment('邮箱验证时间');
$table->timestamp('created_at')->nullable()->comment('创建时间');
$table->timestamp('updated_at')->nullable()->comment('更新时间');
```

**快捷方法拆解：`personal_access_tokens` 表**

```php
// 之前
$table->morphs('tokenable');
$table->timestamps();

// 之后
$table->string('tokenable_type')->comment('令牌主体类型');
$table->unsignedBigInteger('tokenable_id')->comment('令牌主体 ID');
$table->timestamp('created_at')->nullable()->comment('创建时间');
$table->timestamp('updated_at')->nullable()->comment('更新时间');

$table->index(['tokenable_type', 'tokenable_id']);
```

**给已有表加列：`sys_users`**

```php
// 之前
$table->string('phone', 32)->nullable()->after('email');
$table->boolean('status')->default(true)->after('password');

// 之后
$table->string('phone', 32)->nullable()->after('email')->comment('手机号');
$table->boolean('status')->default(true)->after('password')->comment('状态');
```

**带外键的长链：`mes_product_versions`**

```php
$table->foreignId('product_id')->comment('产品 ID')->constrained('mes_products')->restrictOnDelete();
```

## Related

- `.ai/rules/database-migrations.md`：迁移列注释规则原文，含外键注释顺序、多列快捷方法拆解与“已应用迁移只影响全新安装”的说明。
- `database/migrations/0001_01_01_000000_create_users_table.php`：基础表与多表迁移的注释写法。
- `database/migrations/2026_11_14_143418_create_personal_access_tokens_table.php`：拆 `morphs()` 并补回复合索引。
- `database/migrations/2026_11_14_180923_create_sys_departments_and_add_user_position.php`：给已有表批量加列时的注释写法。
- `database/migrations/2026_11_15_090000_create_organization_tables.php`：外键列注释（工厂 → 车间 → 产线）。
