<?php

use App\Core\Services\BusinessDay;

test('business day boundaries are the shanghai day expressed in utc', function () {
    expect(BusinessDay::startUtc('2026-11-16')->format('Y-m-d H:i:s'))->toBe('2026-11-15 16:00:00')
        ->and(BusinessDay::endUtc('2026-11-16')->format('Y-m-d H:i:s'))->toBe('2026-11-16 16:00:00');
});

test('the application stores timestamps in utc while business dates use shanghai time', function () {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(config('app.business_timezone'))->toBe('Asia/Shanghai')
        ->and(BusinessDay::timezone())->toBe('Asia/Shanghai');
});
