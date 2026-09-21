import { useState } from 'react';
import SearchableSelect from './searchable-select';

interface OfferOption {
    id: string;
    name: string;
}

interface CampaignOffer {
    offer_id: string;
    current_views: string;
    cap_views: string;
}

interface Props {
    options: Record<string, string>;
    value: CampaignOffer[];
    onChange: (rows: CampaignOffer[]) => void;
    errors?: Record<string, string>;
}

export default function CampaignOffersRepeater({
    options,
    value,
    onChange,
    errors = {},
}: Props) {

    function addOffer() {
        onChange([
            ...value,
            {
                offer_id: '',
                current_views: '0',
                cap_views: '0',
            },
        ]);
    }

    function updateOffer(
        index: number,
        field: keyof CampaignOffer,
        fieldValue: string
    ) {
        const rows = [...value];

        rows[index] = {
            ...rows[index],
            [field]: fieldValue,
        };

        onChange(rows);
    }

    function removeOffer(index: number) {
        onChange(value.filter((_, i) => i !== index));
    }

    function getAvailableOptions(index: number) {
        const selectedElsewhere = value
            .filter((_, i) => i !== index)
            .map((row) => row.offer_id)
            .filter(Boolean);

        return Object.fromEntries(
            Object.entries(options).filter(
                ([id]) => !selectedElsewhere.includes(id)
            )
        );
    }

    return (
        <div className="mt-6">

            <div className="mb-2 flex items-center justify-between">
                <label className="text-sm font-medium text-muted-foreground">
                    Campaign Offers
                </label>

                <button
                    type="button"
                    onClick={addOffer}
                    className="rounded-md bg-secondary px-2 py-1 text-sm font-medium"
                >
                    + Add Offer
                </button>
            </div>

            <div className="space-y-3">

                {value.map((row, index) => {

                    const availableOptions = getAvailableOptions(index);

                    return (
                        <div
                            key={index}
                            className="grid grid-cols-[1fr_120px_120px_auto] items-start gap-3 rounded-md border border-border p-3"
                        >

                            {/* Offer */}
                            <div>
                                <label className="block text-xs font-medium text-muted-foreground mb-1">
                                    Offer
                                </label>
                                <SearchableSelect
                                    field={`offer-${index}`}
                                    options={availableOptions}
                                    value={row.offer_id}
                                    placeholder="Choose Offer"
                                    onChange={(offerId) =>
                                        updateOffer(
                                            index,
                                            'offer_id',
                                            offerId
                                        )
                                    }
                                />

                                {errors[`offers.${index}.offer_id`] && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {errors[`offers.${index}.offer_id`]}
                                    </p>
                                )}
                            </div>

                            {/* Current views */}
                            <div>
                                <label className="block text-xs font-medium text-muted-foreground mb-1">
                                    Current Views
                                </label>

                                <input
                                    type="number"
                                    min="0"
                                    value={row.current_views}
                                    onChange={(e) =>
                                        updateOffer(
                                            index,
                                            'current_views',
                                            e.target.value
                                        )
                                    }
                                    //disabled
                                    className="w-full rounded-md border border-border px-3 py-1.5 text-sm"
                                />

                                {errors[`offers.${index}.current_views`] && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {errors[`offers.${index}.current_views`]}
                                    </p>
                                )}
                            </div>

                            {/* Cap views */}
                            <div>
                                <label className="block text-xs font-medium text-muted-foreground mb-1">
                                    Cap
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    value={row.cap_views}
                                    onChange={(e) =>
                                        updateOffer(
                                            index,
                                            'cap_views',
                                            e.target.value
                                        )
                                    }
                                    placeholder="Cap"
                                    className="w-full rounded-md border border-border px-3 py-1.5 text-sm"
                                />

                                {errors[`offers.${index}.cap_views`] && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {errors[`offers.${index}.cap_views`]}
                                    </p>
                                )}
                            </div>

                            {/* Remove */}
                            <div>
                                <div className="mb-1 h-[18px]" aria-hidden="true" />
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
        </div>
    );
}