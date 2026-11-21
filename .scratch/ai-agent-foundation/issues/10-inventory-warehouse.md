# 10: 库存与仓库

**What to build:** 仓库归属工厂；库存列表、库存流水、库位按工厂过滤；跨工厂不能入库、出库或调整库存；库存变动日志沿用现有实现，补齐归属校验。

**Blocked by:** 01, 02

**Status:** done

- [x] 仓库新增所属工厂字段，迁移时回填默认工厂
- [x] 库存列表与流水按仓库所属工厂过滤
- [x] 库位查询按所属工厂过滤
- [x] 跨工厂入库、出库、调整返回 403
- [x] 库存流水日志保持现有的入库、出库、调整动作编码
- [x] 库存预警保持全局口径，不纳入本次过滤，接口行为不变
- [x] 现有库存测试通过

## Comments

- 库存走**工厂维度**归属（新接口 `HasFactoryScope`，Guard 双接口分支），不走产线：`line` 范围用户按其 factoryId 看全工厂库存，与 01 §4.3 D 类「通过仓库归属」一致。
- 迁移内回填默认工厂（无工厂时 skip，未分配仓库仅 `all` 可见，fail-closed）；ticket 16 的回填命令幂等兜底，重复绑定无害。
- 仓储下拉 options 对 `none` 用户返回空集（01 §9「403 或空集」）；`StorageLocation::scopeInDataScope` 当前无列表调用方（库位下拉经 Warehouse 过滤），为 §4.3 D 类统一接口预留。
- 审查补 inbound 403 用例（与 outbound/adjust 同走 `apply()` 的 `ensure($location)`）；Guard 类注释同步了双接口设计。
