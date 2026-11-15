<?php

use App\Core\Exceptions\ApiException;
use App\Modules\Quality\Model\InspectionItem;
use App\Modules\Quality\Service\InspectionItemService;

it('refuses a partial save that would break the range invariant', function () {
    $item = InspectionItem::factory()->create();

    expect(fn () => app(InspectionItemService::class)->save(['min_value' => 6], $item))
        ->toThrow(ApiException::class);

    expect($item->fresh()->standard->min_value)->toBe('4.800');
});
