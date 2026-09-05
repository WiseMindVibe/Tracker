<?php

use App\Services\Postbacks\OponiaPostbackAdapter;
use App\Services\Postbacks\PostbackException;
use App\Services\Postbacks\YieldKitPostbackAdapter;

it('normalizes a YieldKit postback', function (): void {
    $event = (new YieldKitPostbackAdapter)->normalize([
        'EVENT_ID' => 'yield-event-1',
        'COMMISSION_ID' => 'yield-commission-1',
        'COMMISSION' => '4.25',
        'SUB_ID' => 'click-uuid',
        'EVENT_TYPE' => 'NEW',
        'STATE' => 'CONFIRMED',
        'MODIFIED_DATE' => '2026-09-04T12:00:00Z',
    ]);

    expect($event)->toMatchArray([
        'external_event_id' => 'yield-event-1',
        'commission_id' => 'yield-commission-1',
        'click_reference' => 'click-uuid',
        'commission' => '4.25',
        'status' => 'CONFIRMED',
        'currency' => 'EUR',
    ]);
});

it('normalizes an Oponia placement click reference', function (): void {
    $event = (new OponiaPostbackAdapter)->normalize([
        'EVENT_ID' => 'oponia-event-1',
        'COMMISSION_ID' => 'oponia-commission-1',
        'COMMISSION' => '2.10',
        'placementId' => 'click-uuid',
        'EVENT_TYPE' => 'UPDATE',
        'STATE' => 'confirmed',
        'CURRENCY' => 'EUR',
        'MODIFIED_DATE' => '2026-09-04T12:00:00+00:00',
    ]);

    expect($event)->toMatchArray([
        'external_event_id' => 'oponia-event-1',
        'click_reference' => 'click-uuid',
        'status' => 'confirmed',
        'event_type' => 'UPDATE',
    ]);
});

it('rejects a postback without a click reference', function (): void {
    (new YieldKitPostbackAdapter)->normalize([
        'EVENT_ID' => 'event-1',
        'COMMISSION_ID' => 'commission-1',
        'COMMISSION' => '1.00',
        'EVENT_TYPE' => 'NEW',
        'STATE' => 'OPEN',
    ]);
})->throws(PostbackException::class);
