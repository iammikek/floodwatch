<?php

declare(strict_types=1);

namespace App\Support\Tooling;

final class ToolResult
{
    private function __construct(
        private bool $ok,
        private mixed $data,
        private ?string $error = null
    ) {}

    public static function ok(mixed $data): self
    {
        return new self(true, $data, null);
    }

    public static function error(string $message): self
    {
        return new self(false, null, $message);
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function data(): mixed
    {
        return $this->data;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
