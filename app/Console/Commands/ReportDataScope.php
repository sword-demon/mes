<?php

namespace App\Console\Commands;

use App\Core\DataScope\DataScope;
use App\Modules\Inventory\Model\Warehouse;
use App\Modules\Organization\Model\Factory;
use App\Modules\System\Model\User;
use App\Modules\System\Service\AccessControlService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('mes:data-scope:report')]
#[Description('列出未配置数据范围的用户、产线范围用户与仓库')]
class ReportDataScope extends Command
{
    public function handle(AccessControlService $access): int
    {
        $hasEnabledFactory = Factory::query()->where('status', true)->exists();

        $missingFactoryUsers = User::query()
            ->whereNull('factory_id')
            ->orderBy('id')
            ->get(['id', 'username', 'name', 'email']);

        $missingLineUsers = User::query()
            ->with('activeRoles.permissions')
            ->withPermission('data.scope.'.DataScope::LINE)
            ->whereDoesntHave('productionLines')
            ->orderBy('id')
            ->get()
            ->filter(fn (User $user): bool => $access->dataScope($user, $access->grantedCodes($user)) === DataScope::LINE)
            ->values();

        $missingFactoryWarehouses = Warehouse::query()
            ->whereNull('factory_id')
            ->orderBy('id')
            ->get(['id', 'code', 'name']);

        $this->reportUsers('未配置工厂的用户', $missingFactoryUsers);
        $this->reportUsers('产线范围但未绑定产线的用户', $missingLineUsers);
        $this->reportWarehouses($missingFactoryWarehouses);

        if (! $hasEnabledFactory) {
            $this->error('未找到启用工厂，请先创建并启用工厂后再上线');

            return self::FAILURE;
        }

        if ($missingFactoryUsers->isEmpty() && $missingLineUsers->isEmpty() && $missingFactoryWarehouses->isEmpty()) {
            $this->info('数据范围配置完整：未发现未配置的用户、产线或仓库。');
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function reportUsers(string $title, Collection $users): void
    {
        $this->warn("{$title}：{$users->count()}");

        if ($users->isEmpty()) {
            return;
        }

        $this->table(
            ['ID', '账号', '姓名'],
            $users->map(fn (User $user): array => [
                $user->id,
                $user->email !== '' ? $user->email : ($user->username ?? (string) $user->id),
                $user->name,
            ])->all(),
        );
    }

    /**
     * @param  Collection<int, Warehouse>  $warehouses
     */
    private function reportWarehouses(Collection $warehouses): void
    {
        $this->warn("未配置工厂的仓库：{$warehouses->count()}");

        if ($warehouses->isEmpty()) {
            return;
        }

        $this->table(
            ['ID', '仓库编码', '仓库名称'],
            $warehouses->map(fn (Warehouse $warehouse): array => [
                $warehouse->id,
                $warehouse->code,
                $warehouse->name,
            ])->all(),
        );
    }
}
