<?php

use App\Models\AffiliateCatalog;
use App\Models\ConversionEvent;
use App\Services\Commissions\CommissionCalculator;
use Illuminate\Support\Collection;

function commissionEvents(array $events): Collection
{
    return collect($events)->map(fn (array $event): ConversionEvent => new ConversionEvent($event));
}

it('calculates YieldKit delta updates and confirmed loss', function (): void {
    $catalog = AffiliateCatalog::factory()->make(['slug' => 'yieldkit', 'commission_mode' => 'delta']);

    $result = (new CommissionCalculator)->calculate($catalog, commissionEvents([
        ['commission' => 5, 'status' => 'OPEN', 'created_at' => 1],
        ['commission' => 2, 'status' => 'OPEN', 'created_at' => 2],
        ['commission' => 4, 'status' => 'CONFIRMED', 'created_at' => 3],
    ]));

    expect($result)->toMatchArray([
        'status' => 'confirmed',
        'value' => 4.0,
        'accumulated' => 7.0,
        'loss' => 3.0,
    ]);
});

it('uses accumulated value when YieldKit rejects with zero', function (): void {
    $catalog = AffiliateCatalog::factory()->make(['slug' => 'yieldkit', 'commission_mode' => 'delta']);

    $result = (new CommissionCalculator)->calculate($catalog, commissionEvents([
        ['commission' => 5, 'status' => 'OPEN', 'created_at' => 1],
        ['commission' => 4, 'status' => 'OPEN', 'created_at' => 2],
        ['commission' => 0, 'status' => 'REJECTED', 'created_at' => 3],
    ]));

    expect($result['value'])->toBe(9.0)
        ->and($result['loss'])->toBe(0.0);
});

it('normalizes positive and negative rejected amounts', function (float $amount): void {
    $catalog = AffiliateCatalog::factory()->make(['slug' => 'yieldkit', 'commission_mode' => 'delta']);

    $result = (new CommissionCalculator)->calculate($catalog, commissionEvents([
        ['commission' => 17, 'status' => 'OPEN', 'created_at' => 1],
        ['commission' => $amount, 'status' => 'REJECTED', 'created_at' => 2],
    ]));

    expect($result['value'])->toBe(17.0);
})->with([0.0, 17.0, -17.0]);

it('keeps only the rejected portion when YieldKit rejects part of an open commission', function (): void {
    $catalog = AffiliateCatalog::factory()->make(['slug' => 'yieldkit', 'commission_mode' => 'delta']);

    $result = (new CommissionCalculator)->calculate($catalog, commissionEvents([
        ['commission' => 7, 'status' => 'OPEN', 'created_at' => 1],
        ['commission' => -6, 'status' => 'REJECTED', 'created_at' => 2],
    ]));

    expect($result)->toMatchArray([
        'value' => 6.0,
        'accumulated' => 7.0,
        'loss' => 1.0,
    ]);
});

it('uses absolute values for Oponia', function (): void {
    $catalog = AffiliateCatalog::factory()->make(['slug' => 'oponia', 'commission_mode' => 'absolute']);

    $result = (new CommissionCalculator)->calculate($catalog, commissionEvents([
        ['commission' => 2, 'status' => 'OPEN', 'created_at' => 1],
        ['commission' => 5, 'status' => 'OPEN', 'created_at' => 2],
        ['commission' => 4, 'status' => 'CONFIRMED', 'created_at' => 3],
    ]));

    expect($result['value'])->toBe(4.0)
        ->and($result['accumulated'])->toBe(5.0);
});

it('uses the configured absolute rule for a YieldKit paid event', function (): void {
    $catalog = AffiliateCatalog::factory()->make(['slug' => 'yieldkit', 'commission_mode' => 'delta']);

    $result = (new CommissionCalculator)->calculate($catalog, commissionEvents([
        ['commission' => 5, 'status' => 'OPEN', 'created_at' => 1],
        ['commission' => 2, 'status' => 'OPEN', 'created_at' => 2],
        ['commission' => 4, 'status' => 'PAID', 'created_at' => 3],
    ]));

    expect($result['status'])->toBe('paid')
        ->and($result['value'])->toBe(4.0)
        ->and($result['loss'])->toBe(3.0);
});
