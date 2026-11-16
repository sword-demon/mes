<?php

namespace App\Modules\Dashboard\Service;

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentAlarm;
use App\Modules\Equipment\Service\EquipmentService;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Production\Model\ProductionException;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionReport;
use App\Modules\Quality\Model\DefectiveProduct;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class DashboardService
{
    private const int TREND_DAYS = 7;

    private const int LATEST_LIMIT = 5;

    public function __construct(private readonly EquipmentService $equipments) {}

    /**
     * @return array<string, mixed>
     */
    public function today(): array
    {
        $start = now($this->businessTimezone())->startOfDay()->utc();
        $end = $start->addDay();

        $orders = ProductionOrder::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->where('status', '!=', ProductionOrder::CANCELLED)
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(planned_quantity), 0) as planned_quantity')
            ->first();

        $reports = ProductionReport::query()
            ->effective()
            ->where('reported_at', '>=', $start)
            ->where('reported_at', '<', $end)
            ->selectRaw(
                'COALESCE(SUM(quantity), 0) as produced_quantity, '
                .'COALESCE(SUM(qualified_quantity), 0) as qualified_quantity, '
                .'COALESCE(SUM(defective_quantity), 0) as defective_quantity',
            )
            ->first();

        return [
            'date' => $start->setTimezone($this->businessTimezone())->toDateString(),
            'order_count' => (int) $orders->getAttribute('order_count'),
            'planned_quantity' => $this->decimal($orders->getAttribute('planned_quantity')),
            'produced_quantity' => $this->decimal($reports->getAttribute('produced_quantity')),
            'qualified_quantity' => $this->decimal($reports->getAttribute('qualified_quantity')),
            'defective_quantity' => $this->decimal($reports->getAttribute('defective_quantity')),
            'running_equipment_count' => Equipment::query()
                ->enabled()
                ->where('status', Equipment::RUNNING)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function productionTrend(): array
    {
        [$start, $end] = $this->recentDaysRange();

        $totals = [];

        // shortcut: a bounded 7-day range is aggregated in PHP; move to a SQL rollup if daily volume grows.
        ProductionReport::query()
            ->effective()
            ->where('reported_at', '>=', $start->utc())
            ->where('reported_at', '<', $end->addDay()->utc())
            ->get(['reported_at', 'quantity'])
            ->each(function (ProductionReport $report) use (&$totals): void {
                $date = $report->reported_at->copy()->setTimezone($this->businessTimezone())->toDateString();
                $totals[$date] = ($totals[$date] ?? 0) + (float) $report->quantity;
            });

        $items = [];

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $key = $date->toDateString();
            $items[] = ['date' => $key, 'quantity' => $this->decimal($totals[$key] ?? 0)];
        }

        return ['days' => self::TREND_DAYS, 'items' => $items];
    }

    /**
     * @return array<string, mixed>
     */
    public function productionLines(): array
    {
        [$start, $end] = $this->recentDaysRange();

        $totals = ProductionReport::query()
            ->join('mes_production_tasks as tasks', 'tasks.id', '=', 'mes_production_reports.production_task_id')
            ->join('mes_production_orders as orders', 'orders.id', '=', 'tasks.production_order_id')
            ->effective()
            ->where('mes_production_reports.reported_at', '>=', $start->utc())
            ->where('mes_production_reports.reported_at', '<', $end->addDay()->utc())
            ->selectRaw(
                'COALESCE(tasks.production_line_id, orders.production_line_id) as production_line_id, '
                .'COALESCE(SUM(mes_production_reports.quantity), 0) as quantity',
            )
            ->groupByRaw('COALESCE(tasks.production_line_id, orders.production_line_id)')
            ->pluck('quantity', 'production_line_id');

        return [
            'items' => ProductionLine::query()
                ->where('status', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (ProductionLine $line): array => [
                    'production_line_id' => $line->id,
                    'code' => $line->code,
                    'name' => $line->name,
                    'quantity' => $this->decimal($totals[$line->id] ?? 0),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function quality(): array
    {
        $reports = ProductionReport::query()
            ->effective()
            ->selectRaw(
                'COALESCE(SUM(qualified_quantity), 0) as qualified_quantity, '
                .'COALESCE(SUM(defective_quantity), 0) as defective_quantity',
            )
            ->first();

        $qualified = (float) $reports->getAttribute('qualified_quantity');
        $defective = (float) $reports->getAttribute('defective_quantity');
        $total = $qualified + $defective;

        // Quality registrations are a distribution on their own; they do not reconcile report quantities.
        $defectReasons = DefectiveProduct::query()
            ->whereHas(
                'productionOrder',
                fn (Builder $order) => $order->where('status', '!=', ProductionOrder::CANCELLED),
            )
            ->join('mes_defect_codes', 'mes_defect_codes.id', '=', 'mes_defective_products.defect_code_id')
            ->selectRaw(
                'mes_defect_codes.id as defect_code_id, '
                .'mes_defect_codes.code as code, '
                .'mes_defect_codes.name as name, '
                .'mes_defect_codes.category as category, '
                .'COALESCE(SUM(mes_defective_products.quantity), 0) as quantity',
            )
            ->groupBy('mes_defect_codes.id', 'mes_defect_codes.code', 'mes_defect_codes.name', 'mes_defect_codes.category')
            ->orderByDesc('quantity')
            ->orderBy('mes_defect_codes.code')
            ->get()
            ->map(fn (DefectiveProduct $row): array => [
                'defect_code_id' => (int) $row->getAttribute('defect_code_id'),
                'code' => $row->getAttribute('code'),
                'name' => $row->getAttribute('name'),
                'category' => $row->getAttribute('category'),
                'quantity' => $this->decimal($row->getAttribute('quantity')),
            ])
            ->all();

        return [
            'qualified_quantity' => $this->decimal($qualified),
            'defective_quantity' => $this->decimal($defective),
            'total_quantity' => $this->decimal($total),
            'qualified_rate' => number_format($total > 0 ? $qualified / $total * 100 : 0, 2, '.', ''),
            'defect_reasons' => $defectReasons,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function equipmentStatus(): array
    {
        $counts = $this->equipments->statusCounts();

        return [
            'items' => array_map(
                fn (string $status): array => [
                    'status' => $status,
                    'label' => Equipment::STATUS_LABELS[$status],
                    'count' => $counts[$status],
                ],
                Equipment::STATUSES,
            ),
        ];
    }

    /**
     * @return EloquentCollection<int, ProductionOrder>
     */
    public function latestOrders(): EloquentCollection
    {
        return ProductionOrder::query()
            ->with(['product:id,code,name', 'productionLine:id,code,name'])
            ->where('status', '!=', ProductionOrder::CANCELLED)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LATEST_LIMIT)
            ->get();
    }

    /**
     * @return EloquentCollection<int, ProductionReport>
     */
    public function latestReports(): EloquentCollection
    {
        return ProductionReport::query()
            ->effective()
            ->with([
                'productionTask:id,code,production_order_id,product_id,planned_quantity,produced_quantity,status',
                'productionTask.productionOrder:id,code',
                'productionTask.product:id,code,name',
                'employee:id,name',
            ])
            ->orderByDesc('reported_at')
            ->orderByDesc('id')
            ->limit(self::LATEST_LIMIT)
            ->get();
    }

    /**
     * @return EloquentCollection<int, ProductionException>
     */
    public function latestExceptions(): EloquentCollection
    {
        return ProductionException::query()
            ->with(['productionOrder:id,code,product_id', 'productionOrder.product:id,code,name'])
            ->orderByDesc('reported_at')
            ->orderByDesc('id')
            ->limit(self::LATEST_LIMIT)
            ->get();
    }

    /**
     * @return EloquentCollection<int, EquipmentAlarm>
     */
    public function latestAlarms(): EloquentCollection
    {
        return EquipmentAlarm::query()
            ->with('equipment:id,code,name,type,status')
            ->orderByDesc('triggered_at')
            ->orderByDesc('id')
            ->limit(self::LATEST_LIMIT)
            ->get();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function recentDaysRange(): array
    {
        $end = now($this->businessTimezone())->startOfDay();

        return [$end->subDays(self::TREND_DAYS - 1), $end];
    }

    private function businessTimezone(): string
    {
        return (string) config('app.business_timezone', 'Asia/Shanghai');
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 3, '.', '');
    }
}
