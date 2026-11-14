<?php

use App\Modules\Bom\Model\Bom;
use App\Modules\Bom\Model\BomItem;
use App\Modules\Material\Model\Material;
use App\Modules\Product\Model\ProductVersion;

it('lists boms with their product version and filters by audit status', function () {
    $actor = userWithPermissions(['basic.boms.index']);
    $approvedVersion = ProductVersion::factory()->create();
    $draftVersion = ProductVersion::factory()->create();

    Bom::factory()->create([
        'product_version_id' => $approvedVersion->id,
        'version' => 'V1.0',
        'audit_status' => Bom::APPROVED,
    ]);
    Bom::factory()->create([
        'product_version_id' => $draftVersion->id,
        'version' => 'V1.0',
        'audit_status' => Bom::DRAFT,
    ]);

    bearer($actor)->getJson('/api/v1/basic-data/boms?'.http_build_query(['audit_status' => 'approved']))
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.audit_status', 'approved')
        ->assertJsonPath('data.items.0.product_version.version', $approvedVersion->version)
        ->assertJsonPath('data.items.0.product_version.product.id', $approvedVersion->product_id);
});

it('returns 403 when listing boms without the permission', function () {
    bearer(userWithPermissions([]))->getJson('/api/v1/basic-data/boms')
        ->assertForbidden()
        ->assertJsonPath('code', 403);
});

it('keeps boms of a disabled product version out of the enabled list', function () {
    $actor = userWithPermissions(['basic.boms.index']);
    $version = ProductVersion::factory()->create(['status' => false]);
    Bom::factory()->create(['product_version_id' => $version->id]);

    bearer($actor)->getJson('/api/v1/basic-data/boms?status=1')
        ->assertOk()
        ->assertJsonCount(0, 'data.items');

    bearer($actor)->getJson('/api/v1/basic-data/boms')
        ->assertOk()
        ->assertJsonCount(1, 'data.items');
});

it('creates a draft bom for a product version', function () {
    $actor = userWithPermissions(['basic.boms.create']);
    $version = ProductVersion::factory()->create();

    bearer($actor)->postJson('/api/v1/basic-data/boms', [
        'code' => 'BOM-P001-01',
        'product_version_id' => $version->id,
        'version' => 'V1.0',
        'remark' => '首版',
    ])->assertOk()
        ->assertJsonPath('data.code', 'BOM-P001-01')
        ->assertJsonPath('data.version', 'V1.0')
        ->assertJsonPath('data.audit_status', 'draft')
        ->assertJsonPath('data.status', true)
        ->assertJsonPath('data.audited_at', null)
        ->assertJsonPath('data.product_version.product.id', $version->product_id);

    $this->assertDatabaseHas('mes_boms', [
        'code' => 'BOM-P001-01',
        'product_version_id' => $version->id,
        'audit_status' => 'draft',
    ]);
});

it('keeps the bom code unique and the version unique inside a product version', function () {
    $actor = userWithPermissions(['basic.boms.create']);
    $version = ProductVersion::factory()->create();
    $otherVersion = ProductVersion::factory()->create();
    Bom::factory()->create(['code' => 'BOM-001', 'product_version_id' => $version->id, 'version' => 'V1.0']);

    bearer($actor)->postJson('/api/v1/basic-data/boms', [
        'code' => 'BOM-001',
        'product_version_id' => $otherVersion->id,
        'version' => 'V1.0',
    ])->assertUnprocessable()
        ->assertJsonPath('message', '编码已存在');

    bearer($actor)->postJson('/api/v1/basic-data/boms', [
        'code' => 'BOM-002',
        'product_version_id' => $version->id,
        'version' => 'V1.0',
    ])->assertUnprocessable()
        ->assertJsonPath('message', '版本号已存在');

    $siblingVersion = ProductVersion::factory()->create(['product_id' => $version->product_id]);

    bearer($actor)->postJson('/api/v1/basic-data/boms', [
        'code' => 'BOM-003',
        'product_version_id' => $siblingVersion->id,
        'version' => 'V1.0',
    ])->assertUnprocessable()
        ->assertJsonPath('message', '版本号已存在');
});

it('rejects a bom without a product version', function () {
    $actor = userWithPermissions(['basic.boms.create']);

    bearer($actor)->postJson('/api/v1/basic-data/boms', [
        'code' => 'BOM-001',
        'version' => 'V1.0',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['product_version_id'], 'data.errors');
});

it('adds and lists bom items with material, quantity and loss rate', function () {
    $actor = userWithPermissions(['basic.boms.create', 'basic.boms.index']);
    $bom = Bom::factory()->create();
    $material = Material::factory()->create(['code' => 'M001', 'name' => '冷轧钢板']);

    bearer($actor)->postJson("/api/v1/basic-data/boms/{$bom->id}/items", [
        'material_id' => $material->id,
        'quantity' => 2.5,
        'loss_rate' => 1.5,
    ])->assertOk()
        ->assertJsonPath('data.material_id', $material->id)
        ->assertJsonPath('data.quantity', 2.5)
        ->assertJsonPath('data.loss_rate', 1.5)
        ->assertJsonPath('data.material.code', 'M001');

    bearer($actor)->getJson("/api/v1/basic-data/boms/{$bom->id}/items")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.material.name', '冷轧钢板');
});

it('updates a draft bom', function () {
    $actor = userWithPermissions(['basic.boms.update']);
    $bom = Bom::factory()->create(['remark' => '旧备注']);

    bearer($actor)->putJson("/api/v1/basic-data/boms/{$bom->id}", [
        'remark' => '新备注',
    ])->assertOk()
        ->assertJsonPath('data.remark', '新备注');

    expect($bom->refresh()->remark)->toBe('新备注');
});

it('rejects a duplicate material and a non-positive quantity in a bom item', function () {
    $actor = userWithPermissions(['basic.boms.create']);
    $bom = Bom::factory()->create();
    $material = Material::factory()->create();
    BomItem::factory()->create(['bom_id' => $bom->id, 'material_id' => $material->id]);

    bearer($actor)->postJson("/api/v1/basic-data/boms/{$bom->id}/items", [
        'material_id' => $material->id,
        'quantity' => 0,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['material_id', 'quantity'], 'data.errors');
});

it('updates and deletes a bom item', function () {
    $actor = userWithPermissions(['basic.boms.update']);
    $bom = Bom::factory()->create();
    $item = BomItem::factory()->create(['bom_id' => $bom->id, 'quantity' => 1]);

    bearer($actor)->putJson("/api/v1/basic-data/boms/{$bom->id}/items/{$item->id}", [
        'quantity' => 3.125,
        'loss_rate' => 2,
    ])->assertOk()
        ->assertJsonPath('data.quantity', 3.125)
        ->assertJsonPath('data.loss_rate', 2);

    bearer($actor)->deleteJson("/api/v1/basic-data/boms/{$bom->id}/items/{$item->id}")
        ->assertOk();

    expect(BomItem::query()->whereKey($item->id)->exists())->toBeFalse();
});

it('keeps an item reachable only through its own bom', function () {
    $actor = userWithPermissions(['basic.boms.update']);
    $bom = Bom::factory()->create();
    $other = Bom::factory()->create();
    $item = BomItem::factory()->create(['bom_id' => $bom->id]);

    bearer($actor)->putJson("/api/v1/basic-data/boms/{$other->id}/items/{$item->id}", [
        'quantity' => 9,
    ])->assertNotFound();
});

it('requires an item before submitting a bom for audit', function () {
    $actor = userWithPermissions(['basic.boms.submit']);
    $bom = Bom::factory()->create();

    bearer($actor)->postJson("/api/v1/basic-data/boms/{$bom->id}/submit")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'BOM 至少需要一个子项才能提交审核');
});

it('moves a bom from draft to pending and locks its content', function () {
    $actor = userWithPermissions(['basic.boms.update', 'basic.boms.submit']);
    $bom = Bom::factory()->create();
    BomItem::factory()->create(['bom_id' => $bom->id]);

    bearer($actor)->postJson("/api/v1/basic-data/boms/{$bom->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.audit_status', 'pending');

    bearer($actor)->putJson("/api/v1/basic-data/boms/{$bom->id}", ['code' => 'BOM-CHANGED'])
        ->assertForbidden()
        ->assertJsonPath('message', 'BOM 已提交审核，不可修改，请新建版本');
});

it('only approves a pending bom and records the audit time', function () {
    $actor = userWithPermissions(['basic.boms.audit']);
    $draft = Bom::factory()->create();
    $pending = Bom::factory()->create(['audit_status' => Bom::PENDING]);

    bearer($actor)->postJson("/api/v1/basic-data/boms/{$draft->id}/approve")
        ->assertUnprocessable()
        ->assertJsonPath('message', '只有待审核的 BOM 才能审核通过');

    bearer($actor)->postJson("/api/v1/basic-data/boms/{$pending->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.audit_status', 'approved');

    expect($pending->refresh()->audited_at)->not->toBeNull();
});

it('locks an approved bom but still allows disabling it', function () {
    $actor = userWithPermissions(['basic.boms.update', 'basic.boms.create']);
    $bom = Bom::factory()->create(['audit_status' => Bom::APPROVED, 'audited_at' => now()]);
    $item = BomItem::factory()->create(['bom_id' => $bom->id]);
    $material = Material::factory()->create();

    bearer($actor)->putJson("/api/v1/basic-data/boms/{$bom->id}", ['remark' => '偷改'])
        ->assertForbidden()
        ->assertJsonPath('message', 'BOM 已提交审核，不可修改，请新建版本');

    bearer($actor)->postJson("/api/v1/basic-data/boms/{$bom->id}/items", [
        'material_id' => $material->id,
        'quantity' => 1,
    ])->assertForbidden();

    bearer($actor)->putJson("/api/v1/basic-data/boms/{$bom->id}/items/{$item->id}", ['quantity' => 5])
        ->assertForbidden();

    bearer($actor)->deleteJson("/api/v1/basic-data/boms/{$bom->id}/items/{$item->id}")
        ->assertForbidden();

    bearer($actor)->putJson("/api/v1/basic-data/boms/{$bom->id}", ['status' => false])
        ->assertOk()
        ->assertJsonPath('data.status', false);
});
