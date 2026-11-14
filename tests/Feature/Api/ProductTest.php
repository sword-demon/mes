<?php

use App\Modules\Product\Model\Product;
use App\Modules\Product\Model\ProductVersion;

it('lists and filters products for a user holding the product list permission', function () {
    $actor = userWithPermissions(['basic.products.index']);
    Product::factory()->create(['code' => 'P001', 'category' => '成品']);
    Product::factory()->create(['code' => 'P002', 'category' => '部件']);

    bearer($actor)->getJson('/api/v1/basic-data/products?'.http_build_query(['category' => '成品']))
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.code', 'P001');
});

it('returns 403 when listing products without the permission', function () {
    bearer(userWithPermissions([]))->getJson('/api/v1/basic-data/products')
        ->assertForbidden()
        ->assertJsonPath('code', 403);
});

it('creates a product', function () {
    $actor = userWithPermissions(['basic.products.create']);

    bearer($actor)->postJson('/api/v1/basic-data/products', [
        'code' => 'P010',
        'name' => '智能电表',
        'model' => 'DTS-100',
        'specification' => '220V 5(60)A',
        'category' => '成品',
        'unit' => '台',
    ])->assertOk()
        ->assertJsonPath('data.code', 'P010')
        ->assertJsonPath('data.category', '成品')
        ->assertJsonPath('data.status', true);
});

it('returns a clear error when the product code is already taken', function () {
    $actor = userWithPermissions(['basic.products.create']);
    Product::factory()->create(['code' => 'P010']);

    bearer($actor)->postJson('/api/v1/basic-data/products', [
        'code' => 'P010', 'name' => '重复产品', 'category' => '成品', 'unit' => '台',
    ])->assertUnprocessable()
        ->assertJsonPath('message', '编码已存在');
});

it('rejects a category outside the supported list', function () {
    $actor = userWithPermissions(['basic.products.create']);

    bearer($actor)->postJson('/api/v1/basic-data/products', [
        'code' => 'P011', 'name' => '未知分类', 'category' => '其他', 'unit' => '台',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('category', 'data.errors');
});

it('keeps the product code unique across update while allowing the row to keep its own code', function () {
    $actor = userWithPermissions(['basic.products.update']);
    $product = Product::factory()->create(['code' => 'P001']);
    Product::factory()->create(['code' => 'P002']);

    bearer($actor)->putJson("/api/v1/basic-data/products/{$product->id}", ['code' => 'P001'])
        ->assertOk();

    bearer($actor)->putJson("/api/v1/basic-data/products/{$product->id}", ['code' => 'P002'])
        ->assertUnprocessable()
        ->assertJsonPath('message', '编码已存在');
});

it('filters products by keyword and status', function () {
    $actor = userWithPermissions(['basic.products.index']);
    Product::factory()->create(['code' => 'P001', 'name' => '智能电表']);
    Product::factory()->create(['code' => 'P002', 'name' => '智能电表', 'status' => false]);

    bearer($actor)->getJson('/api/v1/basic-data/products?'.http_build_query([
        'keyword' => '智能',
        'status' => '1',
    ]))->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.code', 'P001');
});

it('disables a product through update', function () {
    $actor = userWithPermissions(['basic.products.update']);
    $product = Product::factory()->create(['status' => true]);

    bearer($actor)->putJson("/api/v1/basic-data/products/{$product->id}", ['status' => false])
        ->assertOk()
        ->assertJsonPath('data.status', false);

    expect($product->refresh()->status)->toBeFalse();
});

it('keeps several versions of one product and rejects a duplicate version number', function () {
    $actor = userWithPermissions(['basic.products.create', 'basic.products.index']);
    $product = Product::factory()->create();

    bearer($actor)->postJson("/api/v1/basic-data/products/{$product->id}/versions", [
        'version' => 'V1.0',
        'remark' => '首版',
    ])->assertOk()
        ->assertJsonPath('data.version', 'V1.0')
        ->assertJsonPath('data.product_id', $product->id);

    bearer($actor)->postJson("/api/v1/basic-data/products/{$product->id}/versions", [
        'version' => 'V2.0',
    ])->assertOk();

    bearer($actor)->postJson("/api/v1/basic-data/products/{$product->id}/versions", [
        'version' => 'V1.0',
    ])->assertUnprocessable()
        ->assertJsonPath('message', '版本号已存在');

    bearer($actor)->getJson("/api/v1/basic-data/products/{$product->id}/versions")
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.version', 'V1.0');
});

it('keeps a version reachable only through its own product', function () {
    $actor = userWithPermissions(['basic.products.update']);
    $product = Product::factory()->create();
    $other = Product::factory()->create();
    $version = ProductVersion::factory()->create(['product_id' => $product->id, 'version' => 'V1.0']);

    bearer($actor)->putJson("/api/v1/basic-data/products/{$other->id}/versions/{$version->id}", [
        'remark' => '串门',
    ])->assertNotFound();

    bearer($actor)->putJson("/api/v1/basic-data/products/{$product->id}/versions/{$version->id}", [
        'remark' => '备注更新',
        'status' => false,
    ])->assertOk()
        ->assertJsonPath('data.remark', '备注更新')
        ->assertJsonPath('data.status', false);
});

it('scopes the version number per product and keeps it unique on update', function () {
    $actor = userWithPermissions(['basic.products.create', 'basic.products.update']);
    $product = Product::factory()->create();
    $other = Product::factory()->create();
    $first = ProductVersion::factory()->create(['product_id' => $product->id, 'version' => 'V1.0']);
    ProductVersion::factory()->create(['product_id' => $product->id, 'version' => 'V2.0']);

    bearer($actor)->postJson("/api/v1/basic-data/products/{$other->id}/versions", [
        'version' => 'V1.0',
    ])->assertOk();

    bearer($actor)->putJson("/api/v1/basic-data/products/{$product->id}/versions/{$first->id}", [
        'version' => 'V2.0',
    ])->assertUnprocessable()
        ->assertJsonPath('message', '版本号已存在');
});

it('keeps versions of a disabled product out of the enabled list', function () {
    $actor = userWithPermissions(['basic.products.index']);
    $product = Product::factory()->create(['status' => false]);
    ProductVersion::factory()->create(['product_id' => $product->id]);

    bearer($actor)->getJson("/api/v1/basic-data/products/{$product->id}/versions?status=1")
        ->assertOk()
        ->assertJsonCount(0, 'data.items');

    bearer($actor)->getJson("/api/v1/basic-data/products/{$product->id}/versions")
        ->assertOk()
        ->assertJsonCount(1, 'data.items');
});

it('refuses to delete a product that still owns versions', function () {
    $actor = userWithPermissions(['basic.products.delete']);
    $product = Product::factory()->create();
    ProductVersion::factory()->create(['product_id' => $product->id]);

    bearer($actor)->deleteJson("/api/v1/basic-data/products/{$product->id}")
        ->assertForbidden()
        ->assertJsonPath('message', '产品下还有版本，只能停用');

    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue();
});

it('deletes a product with no versions', function () {
    $actor = userWithPermissions(['basic.products.delete']);
    $product = Product::factory()->create();

    bearer($actor)->deleteJson("/api/v1/basic-data/products/{$product->id}")
        ->assertOk();

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse();
});
