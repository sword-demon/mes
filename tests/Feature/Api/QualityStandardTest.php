<?php

use App\Modules\Quality\Model\DefectCode;
use App\Modules\Quality\Model\InspectionItem;
use App\Modules\System\Model\User;
use Database\Seeders\SystemSeeder;

it('creates and queries an inspection item with its numerical standard', function () {
    $actor = userWithPermissions(['quality.standards.index', 'quality.standards.create']);

    bearer($actor)->postJson('/api/v1/quality/inspection-items', [
        'code' => 'VOLTAGE', 'name' => '工作电压', 'unit' => 'V',
        'standard_value' => 5, 'min_value' => 4.8, 'max_value' => 5.2,
    ])->assertOk()->assertJsonPath('data.standard_value', '5.000')
        ->assertJsonPath('data.status', true);

    bearer($actor)->getJson('/api/v1/quality/inspection-items?keyword=VOLTAGE')
        ->assertOk()->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.min_value', '4.800')
        ->assertJsonPath('data.items.0.max_value', '5.200');
});

it('maintains and filters classified defect codes', function () {
    $actor = userWithPermissions(['quality.defect-codes.index', 'quality.defect-codes.create', 'quality.defect-codes.update']);
    $id = bearer($actor)->postJson('/api/v1/quality/defect-codes', [
        'code' => 'D001', 'name' => '划伤', 'category' => '外观',
    ])->assertOk()->assertJsonPath('data.category', '外观')->json('data.id');

    bearer($actor)->putJson("/api/v1/quality/defect-codes/{$id}", ['category' => '表面', 'status' => false])
        ->assertOk()->assertJsonPath('data.status', false);
    bearer($actor)->getJson('/api/v1/quality/defect-codes?'.http_build_query(['category' => '表面', 'status' => 0]))
        ->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.code', 'D001');
    bearer($actor)->getJson('/api/v1/quality/defect-codes?status=1')
        ->assertOk()->assertJsonCount(0, 'data.items');
});

it('validates the complete range on partial edits and can disable an item', function () {
    $actor = userWithPermissions(['quality.standards.index', 'quality.standards.create', 'quality.standards.update']);
    $id = bearer($actor)->postJson('/api/v1/quality/inspection-items', [
        'code' => 'LENGTH', 'name' => '产品长度', 'unit' => 'mm',
        'standard_value' => 100, 'min_value' => 99, 'max_value' => 101,
    ])->assertOk()->json('data.id');

    bearer($actor)->putJson("/api/v1/quality/inspection-items/{$id}", ['min_value' => 102])
        ->assertUnprocessable()->assertJsonValidationErrors('min_value', 'data.errors');

    bearer($actor)->putJson("/api/v1/quality/inspection-items/{$id}", ['max_value' => 98])
        ->assertUnprocessable()->assertJsonValidationErrors('max_value', 'data.errors');

    bearer($actor)->putJson("/api/v1/quality/inspection-items/{$id}", ['standard_value' => 98])
        ->assertUnprocessable()->assertJsonValidationErrors('standard_value', 'data.errors');

    bearer($actor)->putJson("/api/v1/quality/inspection-items/{$id}", [
        'standard_value' => 100.5, 'min_value' => 98.5, 'max_value' => 102.5,
    ])->assertOk()->assertJsonPath('data.standard_value', '100.500');

    bearer($actor)->getJson('/api/v1/quality/inspection-items?keyword=LENGTH')
        ->assertOk()->assertJsonPath('data.items.0.standard_value', '100.500')
        ->assertJsonPath('data.items.0.min_value', '98.500')
        ->assertJsonPath('data.items.0.max_value', '102.500');

    bearer($actor)->putJson("/api/v1/quality/inspection-items/{$id}", ['name' => '成品长度', 'status' => false])
        ->assertOk()->assertJsonPath('data.name', '成品长度')->assertJsonPath('data.status', false)
        ->assertJsonPath('data.standard_value', '100.500');

    bearer($actor)->getJson('/api/v1/quality/inspection-items?status=1')->assertOk()->assertJsonCount(0, 'data.items');
    bearer($actor)->getJson('/api/v1/quality/inspection-items?status=0')->assertOk()->assertJsonCount(1, 'data.items');
});

it('rejects precision loss and values outside decimal storage bounds', function (mixed $value) {
    $actor = userWithPermissions(['quality.standards.update', 'quality.standards.index']);
    $item = InspectionItem::factory()->create();

    bearer($actor)->putJson("/api/v1/quality/inspection-items/{$item->id}", ['standard_value' => $value])
        ->assertUnprocessable()->assertJsonValidationErrors('standard_value', 'data.errors');

    bearer($actor)->getJson('/api/v1/quality/inspection-items')
        ->assertOk()->assertJsonPath('data.items.0.standard_value', '5.000');
})->with(['four decimals' => '5.0001', 'overflow' => '1000000000', 'underflow' => '-1000000000']);

it('protects every quality maintenance endpoint', function () {
    $this->getJson('/api/v1/quality/inspection-items')->assertUnauthorized();
    $this->getJson('/api/v1/quality/defect-codes')->assertUnauthorized();
    $actor = userWithPermissions([]);
    $item = InspectionItem::factory()->create();
    $defect = DefectCode::factory()->create();

    foreach (['inspection-items' => $item->id, 'defect-codes' => $defect->id] as $path => $id) {
        bearer($actor)->getJson("/api/v1/quality/{$path}")->assertForbidden();
        bearer($actor)->postJson("/api/v1/quality/{$path}", [])->assertForbidden();
        bearer($actor)->putJson("/api/v1/quality/{$path}/{$id}", ['status' => false])->assertForbidden();
    }
});

it('validates required fields and unique quality codes', function () {
    $item = InspectionItem::factory()->create();
    $defect = DefectCode::factory()->create();
    $admin = superAdmin();
    bearer($admin)->postJson('/api/v1/quality/inspection-items', [])->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'name', 'unit', 'standard_value', 'min_value', 'max_value'], 'data.errors');
    bearer($admin)->postJson('/api/v1/quality/defect-codes', [])->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'name', 'category'], 'data.errors');
    bearer($admin)->putJson("/api/v1/quality/inspection-items/{$item->id}", ['code' => $item->code])->assertOk();
    $other = InspectionItem::factory()->create();
    bearer($admin)->putJson("/api/v1/quality/inspection-items/{$other->id}", ['code' => $item->code])
        ->assertUnprocessable()->assertJsonValidationErrors('code', 'data.errors');
    bearer($admin)->postJson('/api/v1/quality/defect-codes', [
        'code' => $defect->code, 'name' => '重复编码', 'category' => '外观',
    ])->assertUnprocessable()->assertJsonValidationErrors('code', 'data.errors');
});

it('lets the quality manager maintain standards', function () {
    $this->seed(SystemSeeder::class);
    $manager = User::query()->where('email', 'quality.manager@mes.test')->firstOrFail();

    bearer($manager)->postJson('/api/v1/quality/inspection-items', [
        'code' => 'TEMP', 'name' => '温度', 'unit' => '℃',
        'standard_value' => 25, 'min_value' => -10, 'max_value' => 60,
    ])->assertOk();
});

it('lets the quality inspector read standards without maintaining them', function () {
    $this->seed(SystemSeeder::class);
    $inspector = User::query()->where('email', 'quality1@mes.test')->firstOrFail();

    bearer($inspector)->getJson('/api/v1/quality/defect-codes')->assertOk();
    bearer($inspector)->postJson('/api/v1/quality/defect-codes', [
        'code' => 'D900', 'name' => '越权', 'category' => '外观',
    ])->assertForbidden();
});

it('lets the production manager read the quality catalog', function () {
    $this->seed(SystemSeeder::class);
    $reader = User::query()->where('email', 'production@mes.test')->firstOrFail();

    bearer($reader)->getJson('/api/v1/quality/inspection-items')->assertOk();
    bearer($reader)->getJson('/api/v1/quality/defect-codes')->assertOk();
});

it('lets the workshop director read the quality catalog', function () {
    $this->seed(SystemSeeder::class);
    $reader = User::query()->where('email', 'workshop@mes.test')->firstOrFail();

    bearer($reader)->getJson('/api/v1/quality/inspection-items')->assertOk();
    bearer($reader)->getJson('/api/v1/quality/defect-codes')->assertOk();
});
