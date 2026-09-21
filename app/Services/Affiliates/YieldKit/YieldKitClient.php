<?php

namespace App\Services\Affiliates\YieldKit;

use App\Services\Affiliates\AffiliateCredentialResolver;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class YieldKitClient
{
    protected const SLUG = 'yieldkit';

    protected string $baseUrl;
    protected string $apiKey;
    protected string $apiSecret;

    public function __construct(AffiliateCredentialResolver $resolver)
    {
        $config = config('affiliates.yieldkit');

        if (empty($config['base_url'])) {
            throw new RuntimeException("Missing config('affiliates.yieldkit.base_url').");
        }

        $this->baseUrl = rtrim($config['base_url'], '/');

        $credentials = $resolver->get(self::SLUG);

        // Adjust these array keys if you named the credential rows
        // something other than "api_key" / "api_secret".
        $this->apiKey = $credentials['api_key']
            ?? throw new RuntimeException('Missing YieldKit api_key credential.');
        $this->apiSecret = $credentials['api_secret']
            ?? throw new RuntimeException('Missing YieldKit api_secret credential.');
    }

    /**
     * Yields every commission row in [start, end], transparently following
     * pagination via the "next" link until it's empty.
     */
    public function fetchCommissionsByDateRange(CarbonInterface $start, CarbonInterface $end, string $dateType = 'sales'): Generator
    {
        $url = "{$this->baseUrl}/commissions/{$dateType}";

        $query = [
            'format' => 'json',
            'start_date' => $start->utc()->format('Y-m-d\TH:i:s\Z'),
            'end_date' => $end->utc()->format('Y-m-d\TH:i:s\Z'),
        ];

        yield from $this->paginate($url, $query);
    }

    /**
     * Yields every commission row from the last $delta days (today included).
     */
    public function fetchCommissionsByDelta(int $delta, string $dateType = 'sales'): Generator
    {
        $url = "{$this->baseUrl}/commissions/{$dateType}";

        $query = [
            'format' => 'json',
            'delta' => $delta,
        ];

        yield from $this->paginate($url, $query);
    }

    protected function paginate(string $url, array $query): Generator
    {
        $nextUrl = null;

        do {
            $response = $nextUrl
                ? Http::withHeaders($this->headers())->get($nextUrl)
                : Http::withHeaders($this->headers())->get($url, $query);

            if ($response->failed()) {
                Log::error('YieldKit API request failed', [
                    'url' => $nextUrl ?? $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $response->throw();
            }

            $data = $response->json();

            foreach ($data['content'] ?? [] as $row) {
                yield $row;
            }

            $nextUrl = $data['next'] ?? null;
        } while (! empty($nextUrl));
    }

    protected function headers(): array
    {
        return [
            'accept' => 'application/json',
            'x-api-key' => $this->apiKey,
            'x-api-secret' => $this->apiSecret,
        ];
    }
}
