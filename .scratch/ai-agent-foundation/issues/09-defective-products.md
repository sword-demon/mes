# 09: 不良品处置

**What to build:** 不良品列表按范围收窄；跨范围不能处置；登记、开始处理、返工、报废、复检留下日志，报废记录数量与原因。

**Blocked by:** 01, 02, 04

**Status:** done

- [x] 不良品列表按订单归属过滤
- [x] 跨范围处置返回 403
- [x] 登记、开始处理、返工、报废、复检各产生一条日志
- [x] 报废日志记录报废数量与报废原因
- [x] 复检日志记录复检结果与复检备注
- [x] 现有不良品测试通过

## Comments

- label() 用非空断言（FK 均为 NOT NULL + restrictOnDelete，PHPStan 实测通过）；与 ticket 08 的 nullsafe 风格存在漂移，评审评估可达路径无 fatal，保留现状。
- rework/recheck 的跨范围 403 未单独测试（guard 调用与 create/start/scrap 相同，fixture 未授对应权限码），已由其他动作验证同一守卫路径。
