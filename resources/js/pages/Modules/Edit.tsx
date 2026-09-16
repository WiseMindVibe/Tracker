import { Link, Head, useForm, usePage, router } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import SearchableSelect from '@/components/searchable-select';
import CountrySelect from '@/components/country-select';
import RepeaterField from '@/components/repeater-field';
import { buildTrackingLink } from '@/lib/tracking-link';
import CampaignOffersRepeater from '@/components/campaign-offers-repeater';
import AffiliateCredentialsRepeater from '@/components/affiliate-credentials-repeater';
import TrafficCredentialsRepeater from '@/components/traffic-credentials-repeater';

interface CountryOption {
    value: string;
    label: string;
    aliases?: string[];
}

interface Titles {
    page: string;
    header: string;
    header_s: string;
}

interface Field {
    field: string;
    label: string;
    type: "text" | "url" | "select" | "country" | "tracking_link";
    placeholder?: string;
    options?: Record<string, string> | CountryOption[];
    default?: string;
    required?: boolean;
}

interface Repeater {
    relation: string;
    label: string;
    fields: {
        field: string;
        label: string;
        type: string;
        placeholder?: string;
        options?: Record<string, string>;
    }[];
    min: number;
}

interface Props {
    module: string;
    titles: Titles;
    fields: Field[];
    repeaters?: Repeater[];
    record: Record<string, unknown>;
    repeaterData?: Record<string, Record<string, string>[]>;
    id: number;
    readOnly?: boolean;
    uniqueConstraint?: [string, string];
    existingCombos?: [string, string][];
    trafficSourceTemplates?: Record<string, string>;
    trafficAccountSlugs?: Record<string, string>;
    trackerBaseUrl?: string;
    trackerRedirectPath?: string;
    uuid?: string | null;
    affiliateFieldDefinitions?: Record<string, { label: string; key: string }[]>;
    trafficFieldDefinitions?: Record<string, { label: string; key: string }[]>;
    merchantIdLabels?: Record<string, string>;
}

export default function Edit({
    module,
    titles,
    fields,
    repeaters = [],
    record,
    repeaterData = {},
    id,
    readOnly = false,
    uniqueConstraint,
    existingCombos = [],
    trafficSourceTemplates,
    trafficAccountSlugs,
    trackerBaseUrl,
    trackerRedirectPath,
    uuid,
    affiliateFieldDefinitions = {},
    trafficFieldDefinitions = {},
    merchantIdLabels = {},


}: Props) {
    const initialData = {
        ...Object.fromEntries(
            fields.map((f) => [f.field, String(record[f.field] ?? f.default ?? "")])
        ),
        ...Object.fromEntries(
            repeaters.map((r) => [
                r.relation,
                repeaterData[r.relation]?.length
                    ? repeaterData[r.relation]
                    : Array.from({ length: r.min }, () =>
                          Object.fromEntries(r.fields.map((f) => [f.field, ""]))
                      ),
            ])
        ),
    };

    const { data, setData, put, processing, errors } = useForm<Record<string, any>>(initialData);

        function optionsFor(field: Field): Record<string, string> {
        const all = (field.options as Record<string, string>) ?? {};
        if (!uniqueConstraint || field.field !== uniqueConstraint[1]) return all;

        const [aField] = uniqueConstraint;
        const aValue = String(data[aField]);
        const taken = new Set(
            existingCombos.filter(([a]) => a === aValue).map(([, b]) => b)
        );

        return Object.fromEntries(Object.entries(all).filter(([value]) => !taken.has(value)));
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/m/${module}/${id}`);
    };

    const merchantIdLabel = merchantIdLabels[String(data.affiliate_account_id ?? "")] ?? "Merchant ID";

    const { flash } = usePage().props as { flash?: { success?: string; error?: string } };

    return (
        <div className="ml-5 mt-5">
            <Head title={`${readOnly ? "View" : "Edit"} ${titles.header_s}`} />

            <div className="flex items-center justify-between pe-20">
                <h1 className="text-2xl font-semibold tracking-tight">
                    {readOnly ? "View" : "Edit"} {titles.header_s}
                </h1>

                <div className='flex items-center gap-4'>
                    {!readOnly && (
                        <button
                            type="button"
                            onClick={handleDelete}
                            className="inline-flex items-center rounded-md bg-destructive px-3 py-1.5 text-sm font-medium text-destructive-foreground hover:bg-destructive/90"
                        >
                            Delete
                        </button>
                    )}

                    
                    <Link
                        href={`/m/${module}`}
                        className="inline-flex items-center rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        Back
                    </Link>
                </div>
                
            </div>

            {flash?.error && (
                <div className="mt-4 rounded-md border border-destructive bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {flash.error}
                </div>
            )}
            
            <form onSubmit={submit} className="mt-6 max-w-lg space-y-4">
                <div className="grid grid-cols-2 gap-x-6 gap-y-4">

                    {fields.map((field) => (
                        <div key={field.field}>
                            <label
                                htmlFor={field.field}
                                className="block text-sm font-medium text-muted-foreground mb-1"
                            >
                                {field.field === "merchant_id" ? merchantIdLabel : field.label}
                                {field.required !== false && <span className="text-destructive"> *</span>}
                            </label>

                            {field.type === "tracking_link" ? (
                                <input
                                    type="text"
                                    readOnly
                                    disabled
                                    value={
                                        buildTrackingLink(uuid ?? null, String(data.traffic_account_id ?? ""),
                                            trafficAccountSlugs ?? {},
                                            trafficSourceTemplates ?? {},
                                            trackerBaseUrl ?? "",
                                            trackerRedirectPath ?? ""
                                        ) || "Select a traffic source to preview"
                                    }
                                    className="w-full rounded-md border border-border bg-muted px-3 py-1.5 text-sm text-muted-foreground"
                                />
                            ) : field.type === "country" ? (
                                <CountrySelect
                                    field={field.field}
                                    options={(field.options as CountryOption[]) ?? []}
                                    value={data[field.field]}
                                    onChange={(value) => setData(field.field, value)}
                                    disabled={readOnly}
                                />
                            ) : field.type === "select" ? (
                                <SearchableSelect
                                    field={field.field}
                                    options={optionsFor(field)}
                                    value={data[field.field]}
                                    placeholder={field.placeholder ?? "Choose an option"}
                                    onChange={(value) => setData(field.field, value)}
                                    disabled={readOnly}
                                />
                            ) : (
                                <input
                                    id={field.field}
                                    type="text"
                                    value={data[field.field]}
                                    placeholder={field.placeholder}
                                    onChange={(e) => setData(field.field, e.target.value)}
                                    className="w-full rounded-md border border-border px-3 py-1.5 text-sm placeholder:text-muted-foreground"
                                    disabled={readOnly}
                                />
                            )}

                            {errors[field.field] && (
                                <p className="mt-1 text-sm text-destructive">
                                    {errors[field.field]}
                                </p>
                            )}
                        </div>
                    ))}
                </div>

                <div className=''>
                    {repeaters.map((repeater) => {
                        if (repeater.relation === 'affiliateCredentials') {
                            return (
                                <AffiliateCredentialsRepeater
                                    key={repeater.relation}
                                    catalogId={String(data.affiliate_catalog_id ?? "")}
                                    definitions={affiliateFieldDefinitions}
                                    value={data[repeater.relation]}
                                    onChange={(rows) => setData(repeater.relation, rows)}
                                    errors={errors}
                                />
                            );
                        }

                        if (repeater.relation === 'trafficCredentials') {
                            return (
                                <TrafficCredentialsRepeater
                                    key={repeater.relation}
                                    catalogId={String(data.traffic_catalog_id ?? "")}
                                    definitions={trafficFieldDefinitions}
                                    value={data[repeater.relation]}
                                    onChange={(rows) => setData(repeater.relation, rows)}
                                    errors={errors}
                                />
                            );
                        }

                        if (repeater.relation === 'offers') {
                            const offerField = repeater.fields.find(
                                (field) => field.field === 'offer_id'
                            );

                            return (
                                <CampaignOffersRepeater
                                    key={repeater.relation}
                                    options={offerField?.options ?? {}}
                                    value={data[repeater.relation]}
                                    onChange={(rows) =>
                                        setData(repeater.relation, rows)
                                    }
                                    errors={errors}
                                />
                            );
                        }

                        return (
                            <RepeaterField
                                key={repeater.relation}
                                relation={repeater.relation}
                                label={repeater.label}
                                fields={repeater.fields}
                                min={repeater.min}
                                value={data[repeater.relation]}
                                onChange={(rows) =>
                                    setData(repeater.relation, rows)
                                }
                                errors={errors}
                            />
                        );
                    })}
                </div>

                {!readOnly && (
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                    >
                        {processing ? "Saving..." : "Save"}
                    </button>
                )}
            </form>
        </div>
    );

    function handleDelete() {
    if (!confirm(`Delete this ${titles.header_s.toLowerCase()}? This cannot be undone.`)) {
        return;
    }
    router.delete(`/m/${module}/${id}`);
}
}


