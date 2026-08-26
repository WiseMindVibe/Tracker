<?php

namespace App\Support\Countries;

class CountryRepository
{
    /** @var Country[]|null */
    protected ?array $countries = null;

    public function all(): array
    {
        return $this->countries ??= collect(config('countries'))
            ->map(fn ($row) => new Country($row['code'], $row['name'], $row['aliases'] ?? []))
            ->all();
    }

    public function find(string $code): ?Country
    {
        $code = strtoupper($code);

        return collect($this->all())->first(fn (Country $c) => $c->code === $code);
    }

    /**
     * Score-ranked search: exact code match first, then alias, then name.
     */
    public function search(string $query, int $limit = 7): array
    {
        $query = strtolower(trim($query));

        if ($query === '') {
            return array_slice($this->all(), 0, $limit);
        }

        return collect($this->all())
            ->map(function (Country $country) use ($query) {
                return [
                    'country' => $country,
                    'score' => $this->score($country, $query),
                ];
            })
            ->filter(fn ($entry) => $entry['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('country')
            ->values()
            ->all();
    }

    protected function score(Country $country, string $query): int
    {
        $code = strtolower($country->code);
        $name = strtolower($country->name);
        $aliases = array_map('strtolower', $country->aliases);

        if ($code === $query) {
            return 100;
        }

        if (in_array($query, $aliases, true)) {
            return 90;
        }

        if (str_starts_with($name, $query)) {
            return 80;
        }

        if (in_array(true, array_map(fn ($a) => str_starts_with($a, $query), $aliases), true)) {
            return 70;
        }

        if (str_contains($name, $query)) {
            return 50;
        }

        if (in_array(true, array_map(fn ($a) => str_contains($a, $query), $aliases), true)) {
            return 40;
        }

        return 0;
    }
}
