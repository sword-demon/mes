<?php

namespace App\Console\Commands;

use App\Core\DataScope\DataScope;
use App\Core\DataScope\DataScopeQuery;
use App\Modules\Inventory\Model\Warehouse;
use App\Modules\Organization\Model\Factory;
use App\Modules\System\Model\User;
use App\Modules\System\Service\AccessControlService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mes:data-scope:backfill')]
#[Description('将未配置的用户、产线范围用户与仓库回填到默认工厂')]
class BackfillDataScope extends Command
{
    private const LINE_PERMISSION = 'data.scope.'.DataScope::LINE;

    public function handle(AccessControlService $access): int
    {
        $factory = Factory::query()->where('status', true)->orderBy('id')->first();

        if ($factory === null) {
            $this->error('未找到启用工厂，无法回填数据范围');

            return self::FAILURE;
        }

        // 只补空值：重跑不会移动管理员已分配到其他工厂的用户或仓库。
        $userCount = User::query()->whereNull('factory_id')->update(['factory_id' => $factory->id]);
        $warehouseCount = Warehouse::query()->whereNull('factory_id')->update(['factory_id' => $factory->id]);

        $lineUserCount = 0;
        $attachmentCount = 0;

        User::query()
            ->with('activeRoles.permissions')
            ->withPermission(self::LINE_PERMISSION)
            ->whereDoesntHave('productionLines')
            ->orderBy('id')
            ->get()
            ->each(function (User $user) use ($access, &$lineUserCount, &$attachmentCount): void {
                if ($access->dataScope($user, $access->grantedCodes($user)) !== DataScope::LINE
                    || $user->factory_id === null) {
                    return;
                }

                $lineIds = DataScopeQuery::scopedProductionLineIds(
                    new DataScope(DataScope::FACTORY, (int) $user->factory_id),
                );
                $attached = $user->productionLines()->syncWithoutDetaching($lineIds)['attached'];

                $lineUserCount++;
                $attachmentCount += count($attached);
            });

        $this->info("已将 {$userCount} 个用户、{$warehouseCount} 个仓库回填到工厂「{$factory->name}」");
        $this->info("已为 {$lineUserCount} 个产线范围用户补充 {$attachmentCount} 条产线关联");

        return self::SUCCESS;
    }
}
