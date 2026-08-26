<?php

namespace App\Modules\ModuleList\Campaign;

use App\Models\Campaign;
use App\Models\CampaignOffer;
use App\Models\CampaignTrafficId;
use App\Models\Offer;
use App\Models\TrafficAccount;
use App\Modules\ModuleConfig;
use App\Modules\Support\Actions\CreateAction;
use App\Modules\Support\Actions\EditAction;
use App\Modules\Support\Columns\ChipListColumn;
use App\Modules\Support\Columns\RelationColumn;
use App\Modules\Support\Columns\TextColumn;
use App\Modules\Support\Columns\ProgressColumn;
use App\Modules\Support\Field\Field;
use App\Modules\Support\Field\Repeater;
use App\Modules\Support\Filters\SelectFilter;
use App\Modules\Support\Table\Table;
use Illuminate\Database\Eloquent\Builder;

class Config extends ModuleConfig
{
    public function titles(): array
    {
        return [
            'page' => 'Campaigns',
            'header' => 'Campaigns',
            'header_s' => 'Campaign'
        ];
    }

    public function model(): string
    {
        return Campaign::class;
    }

    public function table(): Table
    {
        return new Table(
            columns: [
                new TextColumn('id', 'ID'),
                new TextColumn('is_tester', 'Tester'),
                new TextColumn('name', 'Name'),
                new ProgressColumn('offers', 'current_views', 'cap_views', 'Views Progress'),
                new ChipListColumn('trafficIds', 'traffic_campaign_id', 'Traffic Campaign IDs'),
                new TextColumn('country', 'Country'),
                new TextColumn('tracking_link', 'Tracking Link'),
                new RelationColumn('trafficAccount.trafficCatalog', 'name', 'Traffic Source'),
                new TextColumn('uuid', 'UUID'),
                new TextColumn('status', 'Status'),
                new TextColumn('created_at', 'Created At'),
                new TextColumn('updated_at', 'Updated At'),
            ],
            filters: [
                new SelectFilter('status', ['active', 'inactive']),
            ],
            actions: [
                new CreateAction(),
                new EditAction(),
            ],
            defaultSort: [
                'column' => 'id',
                'direction' => 'desc'
            ]
        );
    }

    public function fields(): array
    {
        return [
            new Field(
                field: 'uuid',
                label: 'UUID',
                type: 'text',
                required: false,
                disabled: true
            ),
            new Field(
                field: 'is_tester',
                label: 'Tester',
                type: 'bool',
            ),
            new Field(
                field: 'name',
                label: 'Name',
                type: 'text',
                placeholder: 'Name - GEO - Mobile - 3G'
            ),
            new Field(
                field: 'country',
                label: 'Country',
                type: 'country',
                options: collect(config('countries'))->map(fn($c) => [
                    'value' => $c['code'],
                    'label' => $c['name'],
                    'aliases' => $c['aliases'] ?? [],
                ])->values()->toArray(),
            ),
            new Field(
                field: 'tracking_link',
                label: 'Tracking Link',
                type: 'tracking_link',
                required: false,
            ),
            new Field(
                field: 'traffic_account_id',
                label: 'Traffic Source',
                type: 'select',
                placeholder: 'Choose Traffic Source',
                options: TrafficAccount::query()
                    ->with('trafficCatalog')
                    ->get()
                    ->mapWithKeys(fn($account) => [
                        $account->id => $account->trafficCatalog?->name ?? "Account #{$account->id}",
                    ])
                    ->toArray(),
            ),
            new Field(
                field: 'status',
                label: 'Status',
                type: 'select',
                options: ['active' => 'Active', 'inactive' => 'Inactive'],
                default: 'active',
            ),
        ];
    }

    public function repeaters(): array
    {
        return [
            new Repeater(
                relation: 'trafficIds',
                label: 'Campaign Traffic IDs',
                fields: [
                    new Field(
                        field: 'traffic_campaign_id',
                        label: 'Traffic Campaign ID',
                        type: 'text',
                        placeholder: 'Traffic Campaign ID',
                    ),
                ],
                min: 0,
            ),

            new Repeater(
                relation: 'offers',
                label: 'Campaign Offers',
                fields: [
                    new Field(
                        field: 'offer_id',
                        label: 'Offer',
                        type: 'select',
                        placeholder: 'Choose Offer',
                        options: Offer::query()
                            ->pluck('name', 'id')
                            ->toArray(),
                    ),
                    new Field(
                        field: 'current_views',
                        label: 'Current Views',
                        type: 'text',
                        placeholder: 'Current Views',
                        default: '10',
                        disabled: true,
                    ),
                    new Field(
                        field: 'cap_views',
                        label: 'Cap Views',
                        type: 'text',
                        placeholder: 'Cap Views',
                        default: '0',
                    ),
                ],
                min: 0,
            ),
        ];
    }
}
