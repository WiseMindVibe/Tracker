<?php

use App\Services\Postbacks\Adapters\OponiaPostbackAdapter;
use App\Services\Postbacks\Adapters\YieldKitPostbackAdapter;
use Illuminate\Http\Request;

it('normalizes a YieldKit postback', function (): void {
    $event = (new YieldKitPostbackAdapter)->parse(Request::create('/postback/yieldkit', 'POST', [
        'EVENT_ID' => 'yield-event-1',
        'COMMISSION_ID' => 'yield-commission-1',
        'COMMISSION' => '4.25',
        'SUB_ID' => 'click-uuid',
        'EVENT_TYPE' => 'NEW',
        'STATE' => 'CONFIRMED',
        'MODIFIED_DATE' => '2026-09-04T12:00:00Z',
    ]));

    expect($event->eventId)->toBe('yield-event-1')
        ->and($event->commissionId)->toBe('yield-commission-1')
        ->and($event->clickId)->toBe('click-uuid')
        ->and($event->commission)->toBe(4.25)
        ->and($event->status)->toBe('CONFIRMED')
        ->and($event->currency)->toBe('EUR');
});

it('normalizes an Oponia placement click reference', function (): void {
    $event = (new OponiaPostbackAdapter)->parse(Request::create('/postback/oponia', 'POST', [
        'EVENT_ID' => 'oponia-event-1',
        'COMMISSION_ID' => 'oponia-commission-1',
        'COMMISSION' => '2.10',
        'placementId' => 'click-uuid',
        'EVENT_TYPE' => 'UPDATE',
        'STATE' => 'confirmed',
        'CURRENCY' => 'EUR',
        'MODIFIED_DATE' => '2026-09-04T12:00:00+00:00',
    ]));

    expect($event->eventId)->toBe('oponia-event-1')
        ->and($event->clickId)->toBe('click-uuid')
        ->and($event->status)->toBe('CONFIRMED')
        ->and($event->eventType)->toBe('UPDATE');
});

it('leaves missing required postback fields nullable for controller validation', function (): void {
    $event = (new YieldKitPostbackAdapter)->parse(Request::create('/postback/yieldkit', 'POST', [
        'EVENT_ID' => 'event-1',
        'COMMISSION_ID' => 'commission-1',
        'COMMISSION' => '1.00',
        'EVENT_TYPE' => 'NEW',
        'STATE' => 'OPEN',
    ]));

    expect($event->clickId)->toBeNull();
});
