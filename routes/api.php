<?php

use App\Modules\Bom\Controller\BomController;
use App\Modules\Bom\Controller\BomItemController;
use App\Modules\Inventory\Controller\InventoryController;
use App\Modules\Material\Controller\MaterialController;
use App\Modules\Organization\Controller\EmployeeController;
use App\Modules\Organization\Controller\FactoryController;
use App\Modules\Organization\Controller\ProductionLineController;
use App\Modules\Organization\Controller\TeamController;
use App\Modules\Organization\Controller\WorkshopController;
use App\Modules\Process\Controller\ProcessController;
use App\Modules\Process\Controller\ProcessRouteController;
use App\Modules\Process\Controller\RouteOperationController;
use App\Modules\Product\Controller\ProductController;
use App\Modules\Product\Controller\ProductVersionController;
use App\Modules\Production\Controller\ProcessRecordController;
use App\Modules\Production\Controller\ProductionDispatchController;
use App\Modules\Production\Controller\ProductionOrderController;
use App\Modules\Production\Controller\ProductionReportController;
use App\Modules\Production\Controller\ProductionTaskController;
use App\Modules\Production\Controller\ProductSnController;
use App\Modules\Quality\Controller\DefectCodeController;
use App\Modules\Quality\Controller\DefectiveProductController;
use App\Modules\Quality\Controller\InspectionItemController;
use App\Modules\Quality\Controller\InspectionTaskController;
use App\Modules\System\Controller\AccessTokenController;
use App\Modules\System\Controller\DepartmentController;
use App\Modules\System\Controller\MeController;
use App\Modules\System\Controller\MenuController;
use App\Modules\System\Controller\PermissionController;
use App\Modules\System\Controller\RoleController;
use App\Modules\System\Controller\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/tokens', [AccessTokenController::class, 'store'])
    ->middleware('throttle:login')
    ->name('api.auth.tokens.store');

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::prefix('quality')->name('api.quality.')->group(function (): void {
        Route::get('/defect-codes', [DefectCodeController::class, 'index'])
            ->middleware('permission:quality.defect-codes.index')->name('defect-codes.index');
        Route::post('/defect-codes', [DefectCodeController::class, 'store'])
            ->middleware('permission:quality.defect-codes.create')->name('defect-codes.store');
        Route::put('/defect-codes/{defect}', [DefectCodeController::class, 'update'])
            ->middleware('permission:quality.defect-codes.update')->name('defect-codes.update');
        Route::get('/inspection-items', [InspectionItemController::class, 'index'])
            ->middleware('permission:quality.standards.index')->name('inspection-items.index');
        Route::post('/inspection-items', [InspectionItemController::class, 'store'])
            ->middleware('permission:quality.standards.create')->name('inspection-items.store');
        Route::put('/inspection-items/{item}', [InspectionItemController::class, 'update'])
            ->middleware('permission:quality.standards.update')->name('inspection-items.update');

        Route::get('/inspection-tasks/options', [InspectionTaskController::class, 'options'])
            ->middleware('permission:quality.inspections.index')->name('inspection-tasks.options');
        Route::get('/inspection-tasks', [InspectionTaskController::class, 'index'])
            ->middleware('permission:quality.inspections.index')->name('inspection-tasks.index');
        Route::post('/inspection-tasks', [InspectionTaskController::class, 'store'])
            ->middleware('permission:quality.inspections.create')->name('inspection-tasks.store');
        Route::post('/inspection-tasks/{task}/record', [InspectionTaskController::class, 'record'])
            ->middleware('permission:quality.inspections.record')->name('inspection-tasks.record');
        Route::post('/inspection-tasks/{task}/images', [InspectionTaskController::class, 'storeImages'])
            ->middleware('permission:quality.inspections.record')->name('inspection-tasks.images.store');

        Route::get('/defective-products/options', [DefectiveProductController::class, 'options'])
            ->middleware('permission:quality.defects.index|quality.defects.create')->name('defective-products.options');
        Route::get('/defective-products', [DefectiveProductController::class, 'index'])
            ->middleware('permission:quality.defects.index')->name('defective-products.index');
        Route::post('/defective-products', [DefectiveProductController::class, 'store'])
            ->middleware('permission:quality.defects.create')->name('defective-products.store');
        Route::post('/defective-products/{defective}/start', [DefectiveProductController::class, 'start'])
            ->middleware('permission:quality.defects.process')->name('defective-products.start');
        Route::post('/defective-products/{defective}/rework', [DefectiveProductController::class, 'rework'])
            ->middleware('permission:quality.defects.rework')->name('defective-products.rework');
        Route::post('/defective-products/{defective}/scrap', [DefectiveProductController::class, 'scrap'])
            ->middleware('permission:quality.defects.scrap')->name('defective-products.scrap');
        Route::post('/defective-products/{defective}/recheck', [DefectiveProductController::class, 'recheck'])
            ->middleware('permission:quality.defects.recheck')->name('defective-products.recheck');
    });
    Route::get('/me', [MeController::class, 'show'])->name('api.me');

    Route::delete('/auth/tokens', [AccessTokenController::class, 'destroy'])
        ->name('api.auth.tokens.destroy');

    Route::prefix('system')->name('api.system.')->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:system.users.index')->name('users.index');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:system.users.create')->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])
            ->middleware('permission:system.users.update')->name('users.update');
        Route::put('/users/{user}/password', [UserController::class, 'resetPassword'])
            ->middleware('permission:system.users.reset-password')->name('users.password');
        Route::put('/users/{user}/roles', [UserController::class, 'assignRoles'])
            ->middleware('permission:system.users.assign-roles')->name('users.roles');

        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware('permission:system.roles.index')->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('permission:system.roles.create')->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:system.roles.update')->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:system.roles.delete')->name('roles.destroy');

        Route::get('/permissions', [PermissionController::class, 'index'])
            ->middleware('permission:system.permissions.index')->name('permissions.index');

        Route::get('/menus', [MenuController::class, 'index'])
            ->middleware('permission:system.menus.index')->name('menus.index');
        Route::post('/menus', [MenuController::class, 'store'])
            ->middleware('permission:system.menus.create')->name('menus.store');
        Route::put('/menus/{menu}', [MenuController::class, 'update'])
            ->middleware('permission:system.menus.update')->name('menus.update');
        Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])
            ->middleware('permission:system.menus.delete')->name('menus.destroy');

        Route::get('/departments', [DepartmentController::class, 'index'])
            ->middleware('permission:system.departments.index')->name('departments.index');
        Route::post('/departments', [DepartmentController::class, 'store'])
            ->middleware('permission:system.departments.create')->name('departments.store');
        Route::put('/departments/{department}', [DepartmentController::class, 'update'])
            ->middleware('permission:system.departments.update')->name('departments.update');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])
            ->middleware('permission:system.departments.delete')->name('departments.destroy');
    });

    Route::prefix('basic-data')->name('api.basic-data.')->group(function (): void {
        Route::get('/factories', [FactoryController::class, 'index'])
            ->middleware('permission:basic.factories.index')->name('factories.index');
        Route::post('/factories', [FactoryController::class, 'store'])
            ->middleware('permission:basic.factories.create')->name('factories.store');
        Route::put('/factories/{factory}', [FactoryController::class, 'update'])
            ->middleware('permission:basic.factories.update')->name('factories.update');
        Route::delete('/factories/{factory}', [FactoryController::class, 'destroy'])
            ->middleware('permission:basic.factories.delete')->name('factories.destroy');

        Route::get('/workshops', [WorkshopController::class, 'index'])
            ->middleware('permission:basic.workshops.index')->name('workshops.index');
        Route::post('/workshops', [WorkshopController::class, 'store'])
            ->middleware('permission:basic.workshops.create')->name('workshops.store');
        Route::put('/workshops/{workshop}', [WorkshopController::class, 'update'])
            ->middleware('permission:basic.workshops.update')->name('workshops.update');
        Route::delete('/workshops/{workshop}', [WorkshopController::class, 'destroy'])
            ->middleware('permission:basic.workshops.delete')->name('workshops.destroy');

        Route::get('/production-lines', [ProductionLineController::class, 'index'])
            ->middleware('permission:basic.lines.index')->name('production-lines.index');
        Route::post('/production-lines', [ProductionLineController::class, 'store'])
            ->middleware('permission:basic.lines.create')->name('production-lines.store');
        Route::put('/production-lines/{production_line}', [ProductionLineController::class, 'update'])
            ->middleware('permission:basic.lines.update')->name('production-lines.update');
        Route::delete('/production-lines/{production_line}', [ProductionLineController::class, 'destroy'])
            ->middleware('permission:basic.lines.delete')->name('production-lines.destroy');

        Route::get('/teams', [TeamController::class, 'index'])
            ->middleware('permission:basic.teams.index')->name('teams.index');
        Route::post('/teams', [TeamController::class, 'store'])
            ->middleware('permission:basic.teams.create')->name('teams.store');
        Route::put('/teams/{team}', [TeamController::class, 'update'])
            ->middleware('permission:basic.teams.update')->name('teams.update');
        Route::delete('/teams/{team}', [TeamController::class, 'destroy'])
            ->middleware('permission:basic.teams.delete')->name('teams.destroy');

        Route::get('/employees', [EmployeeController::class, 'index'])
            ->middleware('permission:basic.employees.index')->name('employees.index');
        Route::post('/employees', [EmployeeController::class, 'store'])
            ->middleware('permission:basic.employees.create')->name('employees.store');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])
            ->middleware('permission:basic.employees.update')->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])
            ->middleware('permission:basic.employees.delete')->name('employees.destroy');

        Route::get('/products', [ProductController::class, 'index'])
            ->middleware('permission:basic.products.index')->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])
            ->middleware('permission:basic.products.create')->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])
            ->middleware('permission:basic.products.update')->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])
            ->middleware('permission:basic.products.delete')->name('products.destroy');

        Route::get('/materials', [MaterialController::class, 'index'])
            ->middleware('permission:basic.materials.index')->name('materials.index');
        Route::post('/materials', [MaterialController::class, 'store'])
            ->middleware('permission:basic.materials.create')->name('materials.store');
        Route::put('/materials/{material}', [MaterialController::class, 'update'])
            ->middleware('permission:basic.materials.update')->name('materials.update');

        Route::get('/boms', [BomController::class, 'index'])
            ->middleware('permission:basic.boms.index')->name('boms.index');
        Route::post('/boms', [BomController::class, 'store'])
            ->middleware('permission:basic.boms.create')->name('boms.store');
        Route::put('/boms/{bom}', [BomController::class, 'update'])
            ->middleware('permission:basic.boms.update')->name('boms.update');
        Route::post('/boms/{bom}/submit', [BomController::class, 'submit'])
            ->middleware('permission:basic.boms.submit')->name('boms.submit');
        Route::post('/boms/{bom}/approve', [BomController::class, 'approve'])
            ->middleware('permission:basic.boms.audit')->name('boms.approve');

        Route::get('/processes', [ProcessController::class, 'index'])
            ->middleware('permission:basic.processes.index')->name('processes.index');
        Route::post('/processes', [ProcessController::class, 'store'])
            ->middleware('permission:basic.processes.create')->name('processes.store');
        Route::put('/processes/{process}', [ProcessController::class, 'update'])
            ->middleware('permission:basic.processes.update')->name('processes.update');

        // A version is always reached through its product, so a mismatched
        // pair never resolves.
        Route::scopeBindings()->group(function (): void {
            Route::get('/products/{product}/versions', [ProductVersionController::class, 'index'])
                ->middleware('permission:basic.products.index')->name('products.versions.index');
            Route::post('/products/{product}/versions', [ProductVersionController::class, 'store'])
                ->middleware('permission:basic.products.create')->name('products.versions.store');
            Route::put('/products/{product}/versions/{version}', [ProductVersionController::class, 'update'])
                ->middleware('permission:basic.products.update')->name('products.versions.update');
        });

        // Items are reached through their BOM, so a mismatched pair never resolves.
        Route::scopeBindings()->group(function (): void {
            Route::get('/boms/{bom}/items', [BomItemController::class, 'index'])
                ->middleware('permission:basic.boms.index')->name('boms.items.index');
            Route::post('/boms/{bom}/items', [BomItemController::class, 'store'])
                ->middleware('permission:basic.boms.create')->name('boms.items.store');
            Route::put('/boms/{bom}/items/{item}', [BomItemController::class, 'update'])
                ->middleware('permission:basic.boms.update')->name('boms.items.update');
            Route::delete('/boms/{bom}/items/{item}', [BomItemController::class, 'destroy'])
                ->middleware('permission:basic.boms.update')->name('boms.items.destroy');
        });
    });

    Route::prefix('process')->name('api.process.')->group(function (): void {
        Route::get('/routes', [ProcessRouteController::class, 'index'])
            ->middleware('permission:process.routes.index')->name('routes.index');
        Route::post('/routes', [ProcessRouteController::class, 'store'])
            ->middleware('permission:process.routes.create')->name('routes.store');
        Route::put('/routes/{route}', [ProcessRouteController::class, 'update'])
            ->middleware('permission:process.routes.update')->name('routes.update');
        Route::post('/routes/{route}/submit', [ProcessRouteController::class, 'submit'])
            ->middleware('permission:process.routes.submit')->name('routes.submit');
        Route::post('/routes/{route}/approve', [ProcessRouteController::class, 'approve'])
            ->middleware('permission:process.routes.audit')->name('routes.approve');

        // Operations are reached through their route, so a mismatched pair never resolves.
        Route::scopeBindings()->group(function (): void {
            Route::get('/routes/{route}/operations', [RouteOperationController::class, 'index'])
                ->middleware('permission:process.routes.index')->name('routes.operations.index');
            Route::post('/routes/{route}/operations', [RouteOperationController::class, 'store'])
                ->middleware('permission:process.routes.create')->name('routes.operations.store');
            Route::put('/routes/{route}/operations/{operation}', [RouteOperationController::class, 'update'])
                ->middleware('permission:process.routes.update')->name('routes.operations.update');
            Route::delete('/routes/{route}/operations/{operation}', [RouteOperationController::class, 'destroy'])
                ->middleware('permission:process.routes.update')->name('routes.operations.destroy');
        });
    });

    Route::prefix('inventory')->name('api.inventory.')->group(function (): void {
        Route::get('/options', [InventoryController::class, 'options'])
            ->middleware('permission:inventory.index|inventory.inbound|inventory.outbound|inventory.adjust')->name('options');
        Route::get('/transactions', [InventoryController::class, 'transactions'])
            ->middleware('permission:inventory.index|inventory.inbound|inventory.outbound|inventory.adjust')
            ->name('transactions.index');
        Route::get('/', [InventoryController::class, 'index'])
            ->middleware('permission:inventory.index')->name('index');
        Route::post('/inbound', [InventoryController::class, 'inbound'])
            ->middleware('permission:inventory.inbound')->name('inbound');
        Route::post('/outbound', [InventoryController::class, 'outbound'])
            ->middleware('permission:inventory.outbound')->name('outbound');
        Route::post('/adjust', [InventoryController::class, 'adjust'])
            ->middleware('permission:inventory.adjust')->name('adjust');
    });

    Route::prefix('production')->name('api.production.')->group(function (): void {
        Route::get('/orders', [ProductionOrderController::class, 'index'])
            ->middleware('permission:production.orders.index')->name('orders.index');
        Route::post('/orders', [ProductionOrderController::class, 'store'])
            ->middleware('permission:production.orders.create')->name('orders.store');
        Route::put('/orders/{order}', [ProductionOrderController::class, 'update'])
            ->middleware('permission:production.orders.update')->name('orders.update');
        Route::post('/orders/{order}/submit', [ProductionOrderController::class, 'submit'])
            ->middleware('permission:production.orders.submit')->name('orders.submit');
        Route::post('/orders/{order}/approve', [ProductionOrderController::class, 'approve'])
            ->middleware('permission:production.orders.audit')->name('orders.approve');
        Route::post('/orders/{order}/release', [ProductionOrderController::class, 'release'])
            ->middleware('permission:production.orders.release')->name('orders.release');
        Route::post('/orders/{order}/start', [ProductionOrderController::class, 'start'])
            ->middleware('permission:production.orders.start')->name('orders.start');
        Route::post('/orders/{order}/pause', [ProductionOrderController::class, 'pause'])
            ->middleware('permission:production.orders.pause')->name('orders.pause');
        Route::post('/orders/{order}/resume', [ProductionOrderController::class, 'resume'])
            ->middleware('permission:production.orders.resume')->name('orders.resume');
        Route::post('/orders/{order}/complete', [ProductionOrderController::class, 'complete'])
            ->middleware('permission:production.orders.complete')->name('orders.complete');
        Route::post('/orders/{order}/close', [ProductionOrderController::class, 'close'])
            ->middleware('permission:production.orders.close')->name('orders.close');
        Route::post('/orders/{order}/cancel', [ProductionOrderController::class, 'cancel'])
            ->middleware('permission:production.orders.cancel')->name('orders.cancel');

        Route::post('/orders/{order}/tasks', [ProductionTaskController::class, 'store'])
            ->middleware('permission:production.tasks.create')->name('orders.tasks.store');
        Route::get('/tasks', [ProductionTaskController::class, 'index'])
            ->middleware('permission:production.tasks.index')->name('tasks.index');
        Route::put('/tasks/{task}', [ProductionTaskController::class, 'update'])
            ->middleware('permission:production.tasks.update')->name('tasks.update');
        Route::post('/tasks/{task}/pause', [ProductionTaskController::class, 'pause'])
            ->middleware('permission:production.tasks.pause')->name('tasks.pause');
        Route::post('/tasks/{task}/resume', [ProductionTaskController::class, 'resume'])
            ->middleware('permission:production.tasks.resume')->name('tasks.resume');
        Route::post('/tasks/{task}/complete', [ProductionTaskController::class, 'complete'])
            ->middleware('permission:production.tasks.complete')->name('tasks.complete');

        Route::get('/dispatches', [ProductionDispatchController::class, 'index'])
            ->middleware('permission:production.dispatches.index')->name('dispatches.index');
        Route::get('/dispatches/mine', [ProductionDispatchController::class, 'mine'])
            ->middleware('permission:production.dispatches.mine')->name('dispatches.mine');
        Route::post('/dispatches', [ProductionDispatchController::class, 'store'])
            ->middleware('permission:production.dispatches.create')->name('dispatches.store');
        Route::get('/tasks/{task}/operations', [ProductionDispatchController::class, 'operations'])
            ->middleware('permission:production.dispatches.create')->name('tasks.operations');

        Route::get('/reports', [ProductionReportController::class, 'index'])
            ->middleware('permission:production.reports.index')->name('reports.index');
        Route::get('/reports/options', [ProductionReportController::class, 'options'])
            ->middleware('permission:production.reports.create')->name('reports.options');
        Route::get('/reports/tasks/{task}/operations', [ProductionDispatchController::class, 'operations'])
            ->middleware('permission:production.reports.create')->name('reports.tasks.operations');
        Route::post('/reports', [ProductionReportController::class, 'store'])
            ->middleware('permission:production.reports.create')->name('reports.store');
        Route::post('/reports/{report}/reverse', [ProductionReportController::class, 'reverse'])
            ->middleware('permission:production.reports.reverse')->name('reports.reverse');

        Route::get('/sns', [ProductSnController::class, 'index'])
            ->middleware('permission:production.sns.index')->name('sns.index');
        Route::get('/sns/options', [ProductSnController::class, 'options'])
            ->middleware('permission:production.sns.create')->name('sns.options');
        Route::post('/sns', [ProductSnController::class, 'store'])
            ->middleware('permission:production.sns.create')->name('sns.store');
        Route::get('/sns/{productSn}', [ProductSnController::class, 'show'])
            ->middleware('permission:production.sns.index')->name('sns.show');
        Route::put('/sns/{productSn}/bind', [ProductSnController::class, 'bind'])
            ->middleware('permission:production.sns.bind')->name('sns.bind');
        Route::get('/sns/{productSn}/label', [ProductSnController::class, 'label'])
            ->middleware('permission:production.sns.print')->name('sns.label');
        Route::post('/sns/{productSn}/print', [ProductSnController::class, 'markPrinted'])
            ->middleware('permission:production.sns.print')->name('sns.print');
        Route::post('/sns/{productSn}/void', [ProductSnController::class, 'void'])
            ->middleware('permission:production.sns.void')->name('sns.void');

        Route::get('/process-records', [ProcessRecordController::class, 'index'])
            ->middleware('permission:production.flows.index')->name('process-records.index');
        Route::post('/process-records/{record}/start', [ProcessRecordController::class, 'start'])
            ->middleware('permission:production.flows.start')->name('process-records.start');
        Route::post('/process-records/{record}/complete', [ProcessRecordController::class, 'complete'])
            ->middleware('permission:production.flows.complete')->name('process-records.complete');
        Route::post('/process-records/{record}/submit', [ProcessRecordController::class, 'submit'])
            ->middleware('permission:production.flows.submit')->name('process-records.submit');
        Route::post('/process-records/{record}/receive', [ProcessRecordController::class, 'receive'])
            ->middleware('permission:production.flows.receive')->name('process-records.receive');
        Route::post('/process-records/{record}/exception', [ProcessRecordController::class, 'exception'])
            ->middleware('permission:production.flows.exception')->name('process-records.exception');
        Route::post('/process-records/{record}/rework', [ProcessRecordController::class, 'rework'])
            ->middleware('permission:production.flows.rework')->name('process-records.rework');
    });
});
