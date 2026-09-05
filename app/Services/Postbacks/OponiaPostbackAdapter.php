<?php

namespace App\Services\Postbacks;

use Carbon\CarbonImmutable;

class OponiaPostbackAdapter extends YieldKitPostbackAdapter
{
    public function normalize(array $payload): array
    {
        $event = [
            'external_event_id' => $this->value($payload, 'EVENT_ID'),
            'commission_id' => $this->value($payload, 'COMMISSION_ID'),
            'click_reference' => $this->firstValue($payload, ['placementId', 'clickId', 'SUB_ID']),
            'commission' => $this->value($payload, 'COMMISSION'),
            'status' => strtolower($this->value($payload, 'STATE')),
            'event_type' => strtoupper($this->value($payload, 'EVENT_TYPE')),
            'currency' => strtoupper($payload['CURRENCY'] ?? 'EUR'),
            'event_occurred_at' => CarbonImmutable::parse($payload['MODIFIED_DATE'] ?? $payload['CLICK_DATE'] ?? now()),
        ];

        if (! is_numeric($event['commission']) || ! in_array($event['event_type'], ['NEW', 'UPDATE'], true)) {
            throw new PostbackException('Invalid postback data.', 422);
        }

        return $event;
    }

    private function value(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_scalar($value) || trim((string) $value) === '') {
            throw new PostbackException('Missing required postback field.', 422);
        }

        return trim((string) $value);
    }

    private function firstValue(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && trim((string) $payload[$key]) !== '') {
                return trim((string) $payload[$key]);
            }
        }

        throw new PostbackException('Missing click reference.', 422);
    }
}
