<?php

namespace App\Modules\ModuleList\TrafficAccount;

use App\Models\Company;
use App\Models\TrafficAccount;
use App\Models\TrafficCatalog;
use App\Modules\ModuleConfig;
use App\Modules\Support\Actions\CreateAction;
use App\Modules\Support\Actions\EditAction;
use App\Modules\Support\Columns\BadgeColumn;
use App\Modules\Support\Columns\RelationColumn;
use App\Modules\Support\Columns\TextColumn;
use App\Modules\Support\Field\Field;
use App\Modules\Support\Field\Repeater;
use App\Modules\Support\Filters\SelectFilter;
use App\Modules\Support\Table\Table;

class Config extends ModuleConfig
{
    public function titles(): array
    {
        return [
            'page' => 'Traffic Sources',
            'header' => 'Traffic Sources Accounts',
            'header_s' => 'Traffic Source'
        ];
    }

    public function model(): string
    {
        return TrafficAccount::class;
    }

    public function table(): Table
    {
        return new Table(
            columns: [
                new TextColumn('id', 'ID'),
                new RelationColumn('company', 'name', 'Company Owned'),
                new RelationColumn('trafficcatalog', 'name', 'Traffic Source'),
                new TextColumn('status', 'Status'),

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
                'direction' => 'asc'
            ]
        );
    }

    public function uniqueConstraint(): ?array
    {
        return ['company_id', 'traffic_catalog_id'];
    }

    public function fields(): array
    {
        return [
            new Field(
                field: 'company_id',
                label: 'Company',
                type: 'select',
                options: Company::query()->pluck('name', 'id')->toArray(),
            ),
            new Field(
                field: 'traffic_catalog_id',
                label: 'Affiliate Network',
                type: 'select',
                options: TrafficCatalog::query()->pluck('name', 'id')->toArray(),
            ),
            new Field(
                field: 'status',
                label: 'Status',
                type: 'select',
                placeholder: 'Choose Traffic Source',
                options: ['active' => 'Active', 'inactive' => 'Inactive'],
                default: 'active'
            ),
        ];
    }


    public function repeaters(): array
    {
        return [
            new Repeater(
                relation: 'trafficCredentials',
                label: 'Traffic Credentials',
                fields: [
                    new Field(field: 'label', label: 'Label', type: 'text'),
                    new Field(field: 'key', label: 'Key', type: 'text'),
                    new Field(field: 'value', label: 'Value', type: 'text', required: false),
                ],
                min: 0,
            ),
        ];
    }
}
