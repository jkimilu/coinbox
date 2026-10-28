<?php

namespace App\Payments\Data;

use Carbon\CarbonImmutable;

/** Provider-agnostic confirmed payment. Amount is a decimal string to avoid float rounding. */
final readonly class NormalizedPayment
{
    public function __construct(
        public string $providerTransactionId,
        public string $paymentMethod,
        public string $amount,
        public string $currency,
        public CarbonImmutable $paidAt,
        public string $status = 'succeeded',
        public ?string $merchantReference = null,
        public ?string $payerIdentifier = null,
        public ?string $payerName = null,
        public array $metadata = [],
    ) {}
}
