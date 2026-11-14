<?php

use App\Modules\Process\Model\Process;

it('lists processes filtered by type and status', function () {
    $actor = userWithPermissions(['basic.processes.index']);
    Process::factory()->create(['code' => 'OP001', 'name' => 'SMT贴片', 'type' => '加工', 'status' => true]);
    Process::factory()->create(['code' => 'OP002', 'name' => 'AOI检测', 'type' => '检验', 'status' => true]);
    Process::factory()->create(['code' => 'OP003', 'name' => '旧组装', 'type' => '加工', 'status' => false]);

    bearer($actor)->getJson('/api/v1/basic-data/processes?type='.urlencode('加工').'&status=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.code', 'OP001')
        ->assertJsonPath('data.items.0.requires_inspection', false);

    bearer($actor)->getJson('/api/v1/basic-data/processes?keyword=AOI')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.code', 'OP002');
});

it('returns 403 when listing processes without the permission', function () {
    bearer(userWithPermissions([]))->getJson('/api/v1/basic-data/processes')
        ->assertForbidden()
        ->assertJsonPath('code', 403);
});

it('creates a process with its type, standard hours and inspection flag', function () {
    $actor = userWithPermissions(['basic.processes.create']);

    bearer($actor)->postJson('/api/v1/basic-data/processes', [
        'code' => 'OP010',
        'name' => '回流焊',
        'type' => '加工',
        'standard_hours' => 0.6,
        'requires_inspection' => true,
        'description' => '峰值温度 245℃',
    ])->assertOk()
        ->assertJsonPath('data.code', 'OP010')
        ->assertJsonPath('data.type', '加工')
        ->assertJsonPath('data.standard_hours', 0.6)
        ->assertJsonPath('data.requires_inspection', true)
        ->assertJsonPath('data.status', true);

    $this->assertDatabaseHas('mes_processes', ['code' => 'OP010', 'type' => '加工']);
});

it('rejects an unknown process type and a duplicate code', function () {
    $actor = userWithPermissions(['basic.processes.create']);
    Process::factory()->create(['code' => 'OP001']);

    bearer($actor)->postJson('/api/v1/basic-data/processes', [
        'code' => 'OP001',
        'name' => '贴片',
        'type' => '热处理',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'type'], 'data.errors')
        ->assertJsonPath('data.errors.code.0', '编码已存在');
});

it('updates a process and retires it by disabling it', function () {
    $actor = userWithPermissions(['basic.processes.update']);
    $process = Process::factory()->create(['standard_hours' => 0.5]);

    bearer($actor)->putJson("/api/v1/basic-data/processes/{$process->id}", [
        'standard_hours' => 1.25,
    ])->assertOk()
        ->assertJsonPath('data.standard_hours', 1.25);

    bearer($actor)->putJson("/api/v1/basic-data/processes/{$process->id}", [
        'status' => false,
    ])->assertOk()
        ->assertJsonPath('data.status', false);

    expect($process->refresh()->status)->toBeFalse();
});

it('keeps its own code when a process is updated', function () {
    $actor = userWithPermissions(['basic.processes.update']);
    $process = Process::factory()->create(['code' => 'OP001', 'name' => '贴片']);

    bearer($actor)->putJson("/api/v1/basic-data/processes/{$process->id}", [
        'code' => 'OP001',
        'name' => 'SMT贴片',
    ])->assertOk()
        ->assertJsonPath('data.name', 'SMT贴片');
});
