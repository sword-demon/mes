# Database Migration Rules

Applies to `database/migrations/**`.

- Every column must carry a Simplified Chinese comment via `->comment('...')`.
- On foreign-key columns, place `->comment()` before `->constrained()`. After `constrained()` the call attaches to the foreign-key definition, so the column comment is silently lost.
- Shortcut methods that create several columns at once cannot take a per-column comment: `timestamps()` returns a Collection, and `morphs()`/`nullableMorphs()` return `void`. Expand them into individual commented columns and re-add any index they created.
- Columns added to an existing table by a later migration must be commented the same way.
- Editing an already-applied migration only changes fresh installs. When an existing database must receive the comments, ship a separate backfill migration instead of rewriting history.

```php
$table->foreignId('product_id')->comment('产品 ID')->constrained('mes_products')->restrictOnDelete();

$table->timestamp('created_at')->nullable()->comment('创建时间');
$table->timestamp('updated_at')->nullable()->comment('更新时间');
```
