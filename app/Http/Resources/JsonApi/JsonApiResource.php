<?php

namespace App\Http\Resources\JsonApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class JsonApiResource
{
    /**
     * Build a JSON:API document with data, meta, links.
     *
     * @param  array<string, mixed>  $data  Top-level data (resource or collection)
     * @param  array<string, mixed>  $meta  Optional meta object
     * @param  array<string, string>  $links  Optional links object
     */
    public static function document(array $data, array $meta = [], array $links = []): JsonResponse
    {
        $document = ['data' => $data];
        if (! empty($meta)) {
            $document['meta'] = $meta;
        }
        if (! empty($links)) {
            $document['links'] = $links;
        }

        return Response::json($document, 200, [], JSON_UNESCAPED_SLASHES)
            ->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * Build a single resource object (type, id, attributes).
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function resource(string $type, string $id, array $attributes): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'attributes' => $attributes,
        ];
    }

    /**
     * Build a collection of resource objects.
     *
     * @param  array<int, array{type: string, id: string, attributes: array}>  $resources
     */
    public static function collection(array $resources): array
    {
        return $resources;
    }

    /**
     * Build a JSON:API error response.
     *
     * @param  array<int, array{status?: string, title?: string, detail?: string}>  $errors
     */
    public static function errors(array $errors, int $status = 400): JsonResponse
    {
        return Response::json(['errors' => $errors], $status, [], JSON_UNESCAPED_SLASHES)
            ->header('Content-Type', 'application/vnd.api+json');
    }
}
