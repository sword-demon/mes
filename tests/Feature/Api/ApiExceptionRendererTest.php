<?php

use App\Core\Exceptions\ApiException;
use App\Core\Exceptions\ApiExceptionRenderer;

test('a business exception keeps its code, message and payload', function () {
    $data = ApiExceptionRenderer::render(new ApiException('库存不足', 4001, ['available' => 3]))->getData(true);

    expect($data)->toMatchArray([
        'code' => 4001,
        'message' => '库存不足',
        'data' => ['available' => 3],
    ])->and($data)->toHaveKey('timestamp');
});

test('a business exception can carry an explicit http status', function () {
    $response = ApiExceptionRenderer::render(new ApiException('订单已关闭', 4009, null, 409));

    expect($response->getStatusCode())->toBe(409)
        ->and($response->getData(true)['code'])->toBe(4009);
});

test('unexpected errors hide their message outside debug mode', function () {
    config(['app.debug' => false]);

    $data = ApiExceptionRenderer::render(new RuntimeException('SQL 连接串泄露'))->getData(true);

    expect($data['code'])->toBe(500)
        ->and($data['message'])->toBe('Internal Server Error');
});
