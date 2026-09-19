<?php

namespace App\Services\Bakong;

/**
 * One rendered KHQR: the exact EMV string shown to the payer, and its md5.
 *
 * These two travel together everywhere because they are the same fact. Bakong's
 * check_transaction_by_md5 takes "md5 hash got from QR string encryption" — the
 * md5 OF THIS STRING — so a payload without its md5 cannot be verified, and an
 * md5 derived from a different string silently asks about a payment nobody
 * made. Binding them in one immutable object is how they stay in step.
 */
final readonly class BakongQr
{
    public function __construct(
        public string $payload,
        public string $md5,
    ) {}

    public static function fromPayload(string $payload): self
    {
        return new self($payload, md5($payload));
    }
}
