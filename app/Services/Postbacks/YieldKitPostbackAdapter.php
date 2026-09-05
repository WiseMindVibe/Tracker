<?php

namespace App\Services\Postbacks;

use Carbon\CarbonImmutable;

class YieldKitPostbackAdapter
{
    public function normalize(array $payload): array
    {
        return [
            'external_event_id' => $this->required($payload, 'EVENT_ID'),
            'commission_id' => $this->required($payload, 'COMMISSION_ID'),
            'click_reference' => $this->first($payload, ['SUB_ID', 'sub_id', 'ykTag', 'click_id']),
            'commission' => $this->required($payload, 'COMMISSION'),
            'status' => strtoupper($this->required($payload, 'STATE')),
            'event_type' => strtoupper($this->required($payload, 'EVENT_TYPE')),
            'currency' => 'EUR',
            'event_occurred_at' => CarbonImmutable::parse($payload['MODIFIED_DATE'] ?? $payload['SALES_DATE'] ?? now()),
        ] + $this->validate($payload);
    }

    private function validate(array $payload): array
    {
        if (! is_numeric($payload['COMMISSION'] ?? null)) {
            throw new PostbackException('Invalid commission.', 422);
        }

        if (! in_array(strtoupper((string) ($payload['EVENT_TYPE'] ?? '')), ['NEW', 'UPDATE'], true)) {
            throw new PostbackException('Invalid event type.', 422);
        }

        return [];
    }

    private function required(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_scalar($value) || trim((string) $value) === '') {
            throw new PostbackException('Missing required postback field.', 422);
        }

        return trim((string) $value);
    }

    private function first(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && trim((string) $payload[$key]) !== '') {
                return trim((string) $payload[$key]);
            }
        }

        throw new PostbackException('Missing click reference.', 422);
    }
}
