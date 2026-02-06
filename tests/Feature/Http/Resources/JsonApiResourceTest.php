<?php

use App\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Http\JsonResponse;

test('document returns json response with data', function () {
    $data = ['floods' => [], 'incidents' => []];

    $response = JsonApiResource::document($data);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('Content-Type'))->toBe('application/vnd.api+json');
    $decoded = json_decode($response->getContent(), true);
    expect($decoded)->toHaveKey('data');
    expect($decoded['data'])->toBe($data);
});

test('document includes meta when provided', function () {
    $response = JsonApiResource::document([], ['total' => 10]);

    $decoded = json_decode($response->getContent(), true);
    expect($decoded)->toHaveKey('meta');
    expect($decoded['meta']['total'])->toBe(10);
});

test('resource returns type id attributes structure', function () {
    $resource = JsonApiResource::resource('floods', '1', ['description' => 'North Moor']);

    expect($resource['type'])->toBe('floods');
    expect($resource['id'])->toBe('1');
    expect($resource['attributes'])->toBe(['description' => 'North Moor']);
});

test('errors returns json api errors', function () {
    $response = JsonApiResource::errors([
        ['status' => '422', 'title' => 'Validation Failed', 'detail' => 'Invalid input'],
    ], 422);

    expect($response->getStatusCode())->toBe(422);
    $decoded = json_decode($response->getContent(), true);
    expect($decoded)->toHaveKey('errors');
    expect($decoded['errors'])->toHaveCount(1);
    expect($decoded['errors'][0]['status'])->toBe('422');
});
