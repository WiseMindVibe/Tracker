export interface Country {
    code: string;
    name: string;
    aliases: string[];
}

function score(country: Country, query: string): number {
    const code = country.code.toLowerCase();
    const name = country.name.toLowerCase();
    const aliases = (country.aliases ?? []).map((a) => a.toLowerCase());

    if (code === query) return 100;
    if (aliases.includes(query)) return 90;
    if (name.startsWith(query)) return 80;
    if (aliases.some((a) => a.startsWith(query))) return 70;
    if (name.includes(query)) return 50;
    if (aliases.some((a) => a.includes(query))) return 40;

    return 0;
}

export function searchCountries(countries: Country[], query: string, limit = 10): Country[] {
    const q = query.trim().toLowerCase();

    if (!q) {
        return countries.slice(0, limit);
    }

    return countries
        .map((country) => ({ country, score: score(country, q) }))
        .filter((entry) => entry.score > 0)
        .sort((a, b) => b.score - a.score)
        .slice(0, limit)
        .map((entry) => entry.country);
}
