<?php

namespace App\Payments\Data;

final readonly class ProviderTransactionResult
{
    public function __construct(
        public bool $supported,
        public bool $found = false,
        public ?string $status = null,
        public ?string $amount = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}

    public static function unsupported(string $message = 'Operation not supported by this provider.'): self
    {
        return new self(supported: false, message: $message);
    }
}
