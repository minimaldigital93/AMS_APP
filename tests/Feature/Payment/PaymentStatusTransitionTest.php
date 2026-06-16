<?php

use App\Enums\PaymentStatus;
use App\Models\KhqrPayment;

it('treats pending, qr_generated and waiting_payment as the open states', function () {
    expect(PaymentStatus::openValues())
        ->toBe(['pending', 'qr_generated', 'waiting_payment']);

    expect(PaymentStatus::Paid->isOpen())->toBeFalse();
    expect(PaymentStatus::Expired->isOpen())->toBeFalse();
    expect(PaymentStatus::QrGenerated->isOpen())->toBeTrue();
});

it('allows only legal forward transitions', function () {
    expect(PaymentStatus::QrGenerated->canTransitionTo(PaymentStatus::Paid))->toBeTrue();
    expect(PaymentStatus::WaitingPayment->canTransitionTo(PaymentStatus::Rejected))->toBeTrue();
    expect(PaymentStatus::Paid->canTransitionTo(PaymentStatus::Refunded))->toBeTrue();

    // Terminal/illegal moves are refused.
    expect(PaymentStatus::Paid->canTransitionTo(PaymentStatus::Pending))->toBeFalse();
    expect(PaymentStatus::Expired->canTransitionTo(PaymentStatus::Paid))->toBeFalse();
    expect(PaymentStatus::Refunded->canTransitionTo(PaymentStatus::Paid))->toBeFalse();
});

it('throws when a row is moved through an illegal transition', function () {
    $row = KhqrPayment::create([
        'transaction_id' => 'TX-GUARD-1',
        'amount' => 10,
        'currency' => 'USD',
        'status' => PaymentStatus::Paid->value,
        'checkout_payload' => [],
    ]);

    $row->transitionTo(PaymentStatus::Pending);
})->throws(LogicException::class);

it('persists the rejected status on a real row (regression: enum truncation bug)', function () {
    // Pre-fix, status was a DB enum without "rejected" → MySQL strict mode threw
    // on every manual reject. It is now a VARCHAR and must round-trip cleanly.
    $row = KhqrPayment::create([
        'transaction_id' => 'TX-REJECT-1',
        'amount' => 10,
        'currency' => 'USD',
        'status' => PaymentStatus::QrGenerated->value,
        'channel' => 'manual',
        'checkout_payload' => [],
    ]);

    $row->transitionTo(PaymentStatus::Rejected);
    $row->save();

    expect($row->fresh()->status)->toBe('rejected');
});
