<?php

namespace App\Services;

class DataLakeResponse
{
    public function __construct(
        public int $status,
        public ?string $etag,
        public mixed $body
    ) {}
}
