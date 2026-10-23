import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ClipboardEvent, FormEventHandler, ReactNode, useState } from 'react';
import CountrySelect from '@/components/country-select';
import SearchableSelect from '@/components/searchable-select';
import { buildTrackingLink } from '@/lib/tracking-link';

interface CountryOption {
    value: string;
    label: string;
    aliases?: string[];
}

interface OfferRow {
    id?: number;
    offer_id: string;
    cap_views: string;
}

interface TrafficIdRow {
    id?: number;
    traffic_campaign_id: string;
}

interface SourceRow {
    traffic_account_id: string;
    traffic_ids: TrafficIdRow[];
}

interface SavedOffer extends OfferRow {
    id: number;
    current_views: number;
}

interface SavedSource {
    traffic_account_id: string;
    close_requested_at: string | null;
    traffic_ids: { id: number; traffic_campaign_id: string; closed_at: string | null }[];
}

interface Quota {
    offer_id: number;
    traffic_account_id: number;
    quota: number;
    clicks: number;
}

interface Props {
    mode: 'create' | 'edit';
    campaign: {
        id: number | null;
        uuid: string;
        name: string;
        country: string;
        is_tester: boolean;
        fallback_url: string;
        status: string;
    };
    offers: SavedOffer[];
    trafficSources: SavedSource[];
    quotas: Quota[];
    options: {
        countries: CountryOption[];
        offers: Record<string, string>;
        trafficAccounts: Record<string, string>;
    };
    tracking: {
        templates: Record<string, string>;
        slugs: Record<string, string>;
        baseUrl: string;
        redirectPath: string;
    };
}

const inputClass =
    'w-full rounded-md border border-border bg-background px-3 py-1.5 text-sm placeholder:text-muted-foreground disabled:opacity-60';

const buttonSecondary =
    'rounded-md bg-secondary px-2.5 py-1 text-sm font-medium hover:bg-secondary/80';

function Section({ title, hint, action, children }: { title: string; hint?: string; action?: ReactNode; children: ReactNode }) {
    return (
        <section className="rounded-lg border border-border p-5">
            <div className="mb-4 flex items-start justify-between gap-4">
                <div>
                    <h2 className="text-base font-semibold">{title}</h2>
                    {hint && <p className="mt-0.5 text-sm text-muted-foreground">{hint}</p>}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}

function Label({ children }: { children: ReactNode }) {
    return <label className="mb-1 block text-sm font-medium text-muted-foreground">{children}</label>;
}

function FieldError({ message }: { message?: string }) {
    return message ? <p className="mt-1 text-xs text-destructive">{message}</p> : null;
}

function Bar({ value, max }: { value: number; max: number }) {
    const percent = max > 0 ? Math.min(100, Math.round((value / max) * 100)) : 0;

    return (
        <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
            <div
                className={`h-full rounded-full ${percent >= 100 ? 'bg-green-500' : 'bg-primary'}`}
                style={{ width: `${percent}%` }}
            />
        </div>
    );
}

function Badge({ tone, children }: { tone: 'green' | 'amber' | 'red'; children: ReactNode }) {
    const tones = {
        green: 'bg-green-500/15 text-green-700 dark:text-green-400',
        amber: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
        red: 'bg-destructive/15 text-destructive',
    };

    return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${tones[tone]}`}>{children}</span>;
}

/** "334 / 333 / 333" style preview of how a cap is divided between sources. */
function splitPreview(cap: number, sources: number): string | null {
    if (sources < 1 || !Number.isFinite(cap) || cap < 1) return null;
    if (sources === 1) return `${cap} from the single source`;

    const base = Math.floor(cap / sources);
    const extra = cap % sources;

    return extra === 0
        ? `${base} per source`
        : `${base + 1} for ${extra} source${extra > 1 ? 's' : ''}, ${base} for the other${sources - extra > 1 ? 's' : ''}`;
}

/** navigator.clipboard only exists on https/localhost, so fall back to execCommand (works on http://tracker.test). */
async function copyText(text: string): Promise<boolean> {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return true;
        }
    } catch {
        // fall through to the legacy path
    }

    const area = document.createElement('textarea');
    area.value = text;
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();

    let ok = false;
    try {
        ok = document.execCommand('copy');
    } catch {
        ok = false;
    }
    document.body.removeChild(area);

    return ok;
}

export default function Form({ mode, campaign, offers, trafficSources, quotas, options, tracking }: Props) {
    const isEdit = mode === 'edit';
    const { flash } = usePage().props as { flash?: { success?: string; error?: string } };

    const { data, setData, post, put, processing, errors } = useForm<Record<string, any>>({
        uuid: campaign.uuid,
        name: campaign.name,
        country: campaign.country,
        is_tester: campaign.is_tester,
        fallback_url: campaign.fallback_url,
        status: campaign.status,
        offers: offers.map((o) => ({ id: o.id, offer_id: o.offer_id, cap_views: o.cap_views })),
        traffic_sources: trafficSources.map((s) => ({
            traffic_account_id: s.traffic_account_id,
            traffic_ids: s.traffic_ids.map((t) => ({ id: t.id, traffic_campaign_id: t.traffic_campaign_id })),
        })),
    });

    const [pendingRemove, setPendingRemove] = useState<string | null>(null);
    const [copied, setCopied] = useState<string | null>(null);

    const formOffers: OfferRow[] = data.offers;
    const formSources: SourceRow[] = data.traffic_sources;

    // --- lookups for what is already saved --------------------------------------------
    const savedOffers = new Map(offers.map((o) => [o.id, o]));
    const savedSources = new Map(trafficSources.map((s) => [s.traffic_account_id, s]));
    const savedTrafficIds = new Map<number, string | null>();
    trafficSources.forEach((s) => s.traffic_ids.forEach((t) => savedTrafficIds.set(t.id, t.closed_at)));
    const quotaMap = new Map(quotas.map((q) => [`${q.offer_id}:${q.traffic_account_id}`, q]));

    const takenSources = new Set(formSources.map((s) => s.traffic_account_id));
    const availableSources = Object.fromEntries(
        Object.entries(options.trafficAccounts).filter(([id]) => !takenSources.has(id))
    );

    // --- offers -------------------------------------------------------------------------
    function addOffer() {
        setData('offers', [...formOffers, { offer_id: '', cap_views: '0' }]);
    }

    function updateOffer(index: number, patch: Partial<OfferRow>) {
        setData('offers', formOffers.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    }

    function removeOffer(index: number) {
        setData('offers', formOffers.filter((_, i) => i !== index));
    }

    function availableOffers(index: number) {
        const usedElsewhere = formOffers.filter((_, i) => i !== index).map((row) => row.offer_id);

        return Object.fromEntries(Object.entries(options.offers).filter(([id]) => !usedElsewhere.includes(id)));
    }

    // --- traffic sources ---------------------------------------------------------------
    function addSource(accountId: string) {
        if (!accountId) return;
        setData('traffic_sources', [...formSources, { traffic_account_id: accountId, traffic_ids: [] }]);
    }

    function updateSource(index: number, patch: Partial<SourceRow>) {
        setData('traffic_sources', formSources.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    }

    function removeSource(accountId: string) {
        setData('traffic_sources', formSources.filter((s) => s.traffic_account_id !== accountId));
        setPendingRemove(null);
    }

    function askRemoveSource(accountId: string) {
        const saved = savedSources.get(accountId);

        // Nothing saved yet (or no ids to stop): remove right away
        if (!saved || saved.traffic_ids.length === 0) {
            removeSource(accountId);
            return;
        }

        setPendingRemove(accountId);
    }

    function addTrafficId(sourceIndex: number) {
        updateSource(sourceIndex, {
            traffic_ids: [...formSources[sourceIndex].traffic_ids, { traffic_campaign_id: '' }],
        });
    }

    function updateTrafficId(sourceIndex: number, idIndex: number, value: string) {
        updateSource(sourceIndex, {
            traffic_ids: formSources[sourceIndex].traffic_ids.map((row, i) =>
                i === idIndex ? { ...row, traffic_campaign_id: value } : row
            ),
        });
    }

    function removeTrafficId(sourceIndex: number, idIndex: number) {
        updateSource(sourceIndex, {
            traffic_ids: formSources[sourceIndex].traffic_ids.filter((_, i) => i !== idIndex),
        });
    }

    // Pasting "123, 456 789" into an id field creates one row per id
    function handlePaste(sourceIndex: number, idIndex: number, event: ClipboardEvent<HTMLInputElement>) {
        const tokens = event.clipboardData.getData('text').split(/[\s,;]+/).filter(Boolean);
        if (tokens.length < 2) return;

        event.preventDefault();

        const rows = [...formSources[sourceIndex].traffic_ids];
        rows[idIndex] = { ...rows[idIndex], traffic_campaign_id: tokens[0] };
        rows.splice(idIndex + 1, 0, ...tokens.slice(1).map((token) => ({ traffic_campaign_id: token })));

        updateSource(sourceIndex, { traffic_ids: rows });
    }

    function copyLink(key: string, link: string) {
        copyText(link).then((ok) => {
            if (!ok) return;
            setCopied(key);
            setTimeout(() => setCopied((current) => (current === key ? null : current)), 1500);
        });
    }

    // --- submit / delete ------------------------------------------------------------------
    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        if (isEdit) {
            put(`/m/campaigns/${campaign.id}`);
        } else {
            post('/m/campaigns');
        }
    };

    function handleDelete() {
        if (!confirm('Delete this campaign? This cannot be undone.')) return;
        router.delete(`/m/campaigns/${campaign.id}`);
    }

    const sourceCount = formSources.length;

    return (
        <div className="ml-5 mt-5 pb-16">
            <Head title={isEdit ? 'Edit Campaign' : 'Create Campaign'} />

            <div className="flex max-w-4xl items-center justify-between pe-20">
                <h1 className="text-2xl font-semibold tracking-tight">{isEdit ? 'Edit Campaign' : 'Create Campaign'}</h1>

                <div className="flex items-center gap-3">
                    {isEdit && (
                        <button
                            type="button"
                            onClick={handleDelete}
                            className="inline-flex items-center rounded-md bg-destructive px-3 py-1.5 text-sm font-medium text-destructive-foreground hover:bg-destructive/90"
                        >
                            Delete
                        </button>
                    )}
                    <Link
                        href="/m/campaigns"
                        className="inline-flex items-center rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        Back
                    </Link>
                </div>
            </div>

            {flash?.error && (
                <div className="mt-4 max-w-4xl rounded-md border border-destructive bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {flash.error}
                </div>
            )}

            <form onSubmit={submit} className="mt-6 max-w-4xl space-y-5 pe-20">
                {/* ---------------- Details ---------------- */}
                <Section title="Details">
                    <div className="grid grid-cols-2 gap-x-6 gap-y-4">
                        <div>
                            <Label>Name</Label>
                            <input
                                className={inputClass}
                                value={data.name}
                                placeholder="Name - GEO - Mobile - 3G"
                                onChange={(e) => setData('name', e.target.value)}
                            />
                            <FieldError message={errors.name} />
                        </div>

                        <div>
                            <Label>Country</Label>
                            <CountrySelect
                                field="country"
                                options={options.countries}
                                value={data.country}
                                onChange={(value: string) => setData('country', value)}
                            />
                            <FieldError message={errors.country} />
                        </div>

                        <div>
                            <Label>Fallback URL</Label>
                            <input
                                className={inputClass}
                                value={data.fallback_url}
                                placeholder="https://google.com"
                                onChange={(e) => setData('fallback_url', e.target.value)}
                            />
                            <FieldError message={errors.fallback_url} />
                        </div>

                        <div>
                            <Label>Status</Label>
                            <select
                                className={inputClass}
                                value={data.status}
                                onChange={(e) => setData('status', e.target.value)}
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <FieldError message={errors.status} />
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={!!data.is_tester}
                                onChange={(e) => setData('is_tester', e.target.checked)}
                            />
                            Tester campaign
                        </label>

                        <div>
                            <Label>UUID</Label>
                            <input className={`${inputClass} bg-muted text-muted-foreground`} value={data.uuid} readOnly disabled />
                            <FieldError message={errors.uuid} />
                        </div>
                    </div>
                </Section>

                {/* ---------------- Offers ---------------- */}
                <Section
                    title="Offers"
                    hint="The cap is the total for the offer. It is split evenly between the traffic sources below."
                    action={
                        <button type="button" onClick={addOffer} className={buttonSecondary}>
                            + Add offer
                        </button>
                    }
                >
                    {formOffers.length === 0 && (
                        <p className="text-sm text-muted-foreground">No offers yet. Add the offers you want to test.</p>
                    )}

                    <div className="space-y-3">
                        {formOffers.map((row, index) => {
                            const saved = row.id ? savedOffers.get(row.id) : undefined;
                            const cap = Number(row.cap_views);
                            const preview = splitPreview(cap, sourceCount);

                            return (
                                <div
                                    key={row.id ?? `new-${index}`}
                                    className="grid grid-cols-[1fr_130px_170px_auto] items-start gap-3 rounded-md border border-border p-3"
                                >
                                    <div>
                                        <Label>Offer</Label>
                                        <SearchableSelect
                                            field={`offer-${index}`}
                                            options={availableOffers(index)}
                                            value={row.offer_id}
                                            placeholder="Choose offer"
                                            onChange={(value: string) => updateOffer(index, { offer_id: value })}
                                        />
                                        <FieldError message={errors[`offers.${index}.offer_id`]} />
                                    </div>

                                    <div>
                                        <Label>Cap</Label>
                                        <input
                                            type="number"
                                            min="0"
                                            className={inputClass}
                                            value={row.cap_views}
                                            onChange={(e) => updateOffer(index, { cap_views: e.target.value })}
                                        />
                                        {preview && <p className="mt-1 text-xs text-muted-foreground">{preview}</p>}
                                        <FieldError message={errors[`offers.${index}.cap_views`]} />
                                    </div>

                                    <div>
                                        <Label>Delivered</Label>
                                        {saved ? (
                                            <div className="pt-1.5">
                                                <div className="mb-1 text-sm tabular-nums">
                                                    {saved.current_views.toLocaleString()} / {Number(saved.cap_views).toLocaleString()}
                                                </div>
                                                <Bar value={saved.current_views} max={Number(saved.cap_views)} />
                                            </div>
                                        ) : (
                                            <p className="pt-2 text-sm text-muted-foreground">Not saved yet</p>
                                        )}
                                    </div>

                                    <div>
                                        <div className="mb-1 h-[20px]" aria-hidden="true" />
                                        <button
                                            type="button"
                                            onClick={() => removeOffer(index)}
                                            className="rounded-md px-2 py-1.5 text-sm text-destructive hover:bg-muted"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </Section>

                {/* ---------------- Traffic sources ---------------- */}
                <Section
                    title="Traffic sources"
                    hint="Each source gets its own tracking URL and its own share of every offer cap. Create the campaigns at the source with that URL, then add their IDs here."
                >
                    <div className="mb-4 max-w-sm">
                        <Label>Add a traffic source</Label>
                        <SearchableSelect
                            field="add-source"
                            options={availableSources}
                            value=""
                            placeholder={Object.keys(availableSources).length ? 'Choose traffic source' : 'All sources added'}
                            onChange={(value: string) => addSource(value)}
                        />
                        <FieldError message={errors.traffic_sources} />
                    </div>

                    {formSources.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No traffic sources yet. Add one to get its tracking URL.
                        </p>
                    )}

                    <div className="space-y-4">
                        {formSources.map((source, sourceIndex) => {
                            const accountId = source.traffic_account_id;
                            const name = options.trafficAccounts[accountId] ?? `Account #${accountId}`;
                            const saved = savedSources.get(accountId);
                            const link = buildTrackingLink(
                                data.uuid,
                                accountId,
                                tracking.slugs,
                                tracking.templates,
                                tracking.baseUrl,
                                tracking.redirectPath
                            );

                            return (
                                <div key={accountId} className="rounded-md border border-border p-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <h3 className="font-medium">{name}</h3>
                                            {saved?.close_requested_at && <Badge tone="amber">Quota full, stop requested</Badge>}
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => askRemoveSource(accountId)}
                                            className="rounded-md px-2 py-1 text-sm text-destructive hover:bg-muted"
                                        >
                                            Remove source
                                        </button>
                                    </div>

                                    {pendingRemove === accountId && saved && (
                                        <div className="mb-4 rounded-md border border-amber-500/50 bg-amber-500/10 p-3 text-sm">
                                            <p className="font-medium">Stop these campaigns at {name} first</p>
                                            <p className="mt-1 text-muted-foreground">
                                                Removing the source deletes its traffic campaign IDs here. Campaigns that keep running at
                                                the source will send their clicks to the fallback URL.
                                            </p>
                                            <div className="mt-2 flex flex-wrap gap-1.5">
                                                {saved.traffic_ids.map((t) => (
                                                    <code key={t.id} className="rounded bg-background px-1.5 py-0.5 text-xs">
                                                        {t.traffic_campaign_id}
                                                    </code>
                                                ))}
                                            </div>
                                            <div className="mt-3 flex gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => removeSource(accountId)}
                                                    className="rounded-md bg-destructive px-2.5 py-1 text-sm font-medium text-destructive-foreground"
                                                >
                                                    I stopped them, remove source
                                                </button>
                                                <button type="button" onClick={() => setPendingRemove(null)} className={buttonSecondary}>
                                                    Cancel
                                                </button>
                                            </div>
                                        </div>
                                    )}

                                    <Label>Tracking URL</Label>
                                    <div className="flex gap-2">
                                        <input
                                            readOnly
                                            value={link || 'Missing tracking template for this source'}
                                            onFocus={(e) => e.currentTarget.select()}
                                            className="w-full rounded-md border border-border bg-muted px-3 py-1.5 text-xs text-muted-foreground"
                                        />
                                        <button
                                            type="button"
                                            disabled={!link}
                                            onClick={() => copyLink(accountId, link)}
                                            className={`${buttonSecondary} w-20 shrink-0 disabled:opacity-50`}
                                        >
                                            {copied === accountId ? 'Copied' : 'Copy'}
                                        </button>
                                    </div>

                                    <div className="mt-4">
                                        <div className="mb-1 flex items-center justify-between">
                                            <Label>Traffic campaign IDs</Label>
                                            <button type="button" onClick={() => addTrafficId(sourceIndex)} className={buttonSecondary}>
                                                + Add ID
                                            </button>
                                        </div>

                                        {source.traffic_ids.length === 0 && (
                                            <p className="text-sm text-muted-foreground">
                                                Add the ID of every campaign you created at {name}. You can paste several at once.
                                            </p>
                                        )}

                                        <div className="space-y-2">
                                            {source.traffic_ids.map((row, idIndex) => {
                                                const closedAt = row.id ? savedTrafficIds.get(row.id) : undefined;
                                                const errorKey = `traffic_sources.${sourceIndex}.traffic_ids.${idIndex}.traffic_campaign_id`;

                                                return (
                                                    <div key={row.id ?? `new-${idIndex}`}>
                                                        <div className="flex items-center gap-2">
                                                            <input
                                                                className={inputClass}
                                                                value={row.traffic_campaign_id}
                                                                placeholder="Traffic campaign ID"
                                                                onChange={(e) => updateTrafficId(sourceIndex, idIndex, e.target.value)}
                                                                onPaste={(e) => handlePaste(sourceIndex, idIndex, e)}
                                                            />
                                                            {closedAt && <Badge tone="green">Stopped</Badge>}
                                                            {row.id && !closedAt && saved?.close_requested_at && (
                                                                <Badge tone="red">Not confirmed, stop it manually</Badge>
                                                            )}
                                                            <button
                                                                type="button"
                                                                onClick={() => removeTrafficId(sourceIndex, idIndex)}
                                                                className="rounded-md px-2 py-1.5 text-sm text-destructive hover:bg-muted"
                                                            >
                                                                Remove
                                                            </button>
                                                        </div>
                                                        <FieldError message={errors[errorKey]} />
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </Section>

                {/* ---------------- Quota grid ---------------- */}
                {isEdit && quotas.length > 0 && (
                    <Section
                        title="How the traffic is splitting"
                        hint="Clicks delivered / quota for every offer and traffic source. A source stops when all its quotas are full."
                    >
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-muted-foreground">
                                        <th className="py-2 pr-4 font-medium">Offer</th>
                                        {formSources.map((s) => (
                                            <th key={s.traffic_account_id} className="px-3 py-2 font-medium">
                                                {options.trafficAccounts[s.traffic_account_id]}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {formOffers.map((row, index) => (
                                        <tr key={row.id ?? `new-${index}`} className="border-t border-border">
                                            <td className="py-2 pr-4">{options.offers[row.offer_id] ?? '-'}</td>
                                            {formSources.map((s) => {
                                                const cell = quotaMap.get(`${row.offer_id}:${s.traffic_account_id}`);

                                                return (
                                                    <td key={s.traffic_account_id} className="min-w-32 px-3 py-2">
                                                        {cell ? (
                                                            <>
                                                                <div className="mb-1 tabular-nums">
                                                                    {cell.clicks.toLocaleString()} / {cell.quota.toLocaleString()}
                                                                </div>
                                                                <Bar value={cell.clicks} max={cell.quota} />
                                                            </>
                                                        ) : (
                                                            <span className="text-muted-foreground">after save</span>
                                                        )}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Section>
                )}

                <button
                    type="submit"
                    disabled={processing}
                    className="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                >
                    {processing ? 'Saving...' : 'Save campaign'}
                </button>
            </form>
        </div>
    );
}