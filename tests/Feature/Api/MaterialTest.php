<?php

use App\Modules\Material\Model\Material;
use App\Modules\System\Model\User;
use Database\Seeders\SystemSeeder;

it('lists and filters materials by type and keyword for a user holding the material list permission', function () {
    $actor = userWithPermissions(['basic.materials.index']);
    Material::factory()->create(['code' => 'M001', 'name' => '冷轧钢板', 'type' => '原材料']);
    Material::factory()->create(['code' => 'M002', 'name' => '瓦楞纸箱', 'type' => '包装材料']);

    bearer($actor)->getJson('/api/v1/basic-data/materials?'.http_build_query([
        'type' => '原材料',
        'keyword' => '钢板',
    ]))->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.code', 'M001')
        ->assertJsonPath('data.items.0.batch_managed', true);
});

it('returns 403 when listing materials without the permission', function () {
    bearer(userWithPermissions([]))->getJson('/api/v1/basic-data/materials')
        ->assertForbidden()
        ->assertJsonPath('code', 403);
});

it('returns 403 when creating a material without the permission', function () {
    bearer(userWithPermissions([]))->postJson('/api/v1/basic-data/materials', [
        'code' => 'M010', 'name' => '无权限物料', 'type' => '原材料', 'unit' => 'kg',
    ])->assertForbidden()
        ->assertJsonPath('code', 403);
});

it('returns 403 when updating a material without the permission', function () {
    $material = Material::factory()->create();

    bearer(userWithPermissions([]))->putJson("/api/v1/basic-data/materials/{$material->id}", [
        'name' => '越权修改',
    ])->assertForbidden()
        ->assertJsonPath('code', 403);
});

it('creates a material with stock thresholds and batch management', function () {
    $actor = userWithPermissions(['basic.materials.create']);

    bearer($actor)->postJson('/api/v1/basic-data/materials', [
        'code' => 'M010',
        'name' => '助焊剂',
        'type' => '辅助材料',
        'specification' => '500ml',
        'unit' => '瓶',
        'min_stock' => 20,
        'safety_stock' => 60,
        'batch_managed' => true,
    ])->assertOk()
        ->assertJsonPath('data.code', 'M010')
        ->assertJsonPath('data.type', '辅助材料')
        ->assertJsonPath('data.min_stock', 20)
        ->assertJsonPath('data.safety_stock', 60)
        ->assertJsonPath('data.batch_managed', true)
        ->assertJsonPath('data.status', true);

    $this->assertDatabaseHas('mes_materials', [
        'code' => 'M010',
        'type' => '辅助材料',
        'min_stock' => 20,
        'safety_stock' => 60,
        'batch_managed' => true,
        'status' => true,
    ]);
});

it('rejects an empty material payload with every required field', function () {
    $actor = userWithPermissions(['basic.materials.create']);

    bearer($actor)->postJson('/api/v1/basic-data/materials', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'name', 'type', 'unit'], 'data.errors');
});

it('returns a clear error when the material code is already taken', function () {
    $actor = userWithPermissions(['basic.materials.create']);
    Material::factory()->create(['code' => 'M010']);

    bearer($actor)->postJson('/api/v1/basic-data/materials', [
        'code' => 'M010', 'name' => '重复物料', 'type' => '原材料', 'unit' => 'kg',
    ])->assertUnprocessable()
        ->assertJsonPath('message', '编码已存在');
});

it('rejects a material type outside the supported list', function () {
    $actor = userWithPermissions(['basic.materials.create']);

    bearer($actor)->postJson('/api/v1/basic-data/materials', [
        'code' => 'M011', 'name' => '未知类型', 'type' => '其他', 'unit' => 'kg',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('type', 'data.errors');
});

it('rejects negative stock thresholds', function () {
    $actor = userWithPermissions(['basic.materials.create']);

    bearer($actor)->postJson('/api/v1/basic-data/materials', [
        'code' => 'M012',
        'name' => '负库存',
        'type' => '原材料',
        'unit' => 'kg',
        'min_stock' => -1,
        'safety_stock' => -5,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['min_stock', 'safety_stock'], 'data.errors');
});

it('rejects stock thresholds beyond the column precision', function () {
    $actor = userWithPermissions(['basic.materials.create']);

    bearer($actor)->postJson('/api/v1/basic-data/materials', [
        'code' => 'M013',
        'name' => '精度越界',
        'type' => '原材料',
        'unit' => 'kg',
        'min_stock' => 1.2345,
        'safety_stock' => 1_000_000_000,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['min_stock', 'safety_stock'], 'data.errors');
});

it('filters disabled materials out of the enabled list', function () {
    $actor = userWithPermissions(['basic.materials.index']);
    Material::factory()->create(['code' => 'M001', 'status' => true]);
    Material::factory()->create(['code' => 'M002', 'status' => false]);

    bearer($actor)->getJson('/api/v1/basic-data/materials?status=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.code', 'M001');
});

it('keeps the material code unique across update while allowing the row to keep its own code', function () {
    $actor = userWithPermissions(['basic.materials.update']);
    $material = Material::factory()->create(['code' => 'M001']);
    Material::factory()->create(['code' => 'M002']);

    bearer($actor)->putJson("/api/v1/basic-data/materials/{$material->id}", ['code' => 'M001'])
        ->assertOk();

    bearer($actor)->putJson("/api/v1/basic-data/materials/{$material->id}", ['code' => 'M002'])
        ->assertUnprocessable()
        ->assertJsonPath('message', '编码已存在');
});

it('updates stock thresholds and disables a material through update', function () {
    $actor = userWithPermissions(['basic.materials.update']);
    $material = Material::factory()->create([
        'min_stock' => 10,
        'safety_stock' => 30,
        'batch_managed' => false,
        'status' => true,
    ]);

    bearer($actor)->putJson("/api/v1/basic-data/materials/{$material->id}", [
        'min_stock' => 15,
        'safety_stock' => 45,
        'batch_managed' => true,
        'status' => false,
    ])->assertOk()
        ->assertJsonPath('data.min_stock', 15)
        ->assertJsonPath('data.safety_stock', 45)
        ->assertJsonPath('data.batch_managed', true)
        ->assertJsonPath('data.status', false);

    expect($material->refresh()->status)->toBeFalse();

    $this->assertDatabaseHas('mes_materials', [
        'id' => $material->id,
        'min_stock' => 15,
        'safety_stock' => 45,
        'batch_managed' => true,
        'status' => false,
    ]);
});

it('lets the seeded process engineer maintain materials', function () {
    $this->seed(SystemSeeder::class);
    $engineer = User::query()->where('email', 'process@mes.test')->firstOrFail();

    bearer($engineer)->postJson('/api/v1/basic-data/materials', [
        'code' => 'M020',
        'name' => '工艺工程师物料',
        'type' => '辅助材料',
        'unit' => '瓶',
    ])->assertOk();

    $material = Material::query()->where('code', 'M020')->firstOrFail();

    bearer($engineer)->putJson("/api/v1/basic-data/materials/{$material->id}", [
        'min_stock' => 5,
        'safety_stock' => 10,
    ])->assertOk()
        ->assertJsonPath('data.min_stock', 5);
});
