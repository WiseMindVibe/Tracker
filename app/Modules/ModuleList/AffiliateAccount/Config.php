<?php

namespace App\Modules\ModuleList\AffiliateAccount;

use App\Models\AffiliateAccount;
use App\Models\AffiliateCatalog;
use App\Models\Company;
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
            'page' => 'Affiliate Networks',
            'header' => 'Affiliate Networks Accounts',
            'header_s' => 'Affiliate Network'
        ];
    }

    public function model(): string
    {
        return AffiliateAccount::class;
    }

    public function table(): Table
    {
        return new Table(
            columns: [
                new TextColumn('id', 'ID'),
                new RelationColumn('company', 'name', 'Company Owned'),
                new RelationColumn('affiliatecatalog', 'name', 'Affiliate Network'),
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
                'direction' => 'desc'
            ],
        );
    }

    public function uniqueConstraint(): ?array
    {
        return ['company_id', 'affiliate_catalog_id'];
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
                field: 'affiliate_catalog_id',
                label: 'Affiliate Network',
                type: 'select',
                options: AffiliateCatalog::query()->pluck('name', 'id')->toArray(),
            ),
            new Field(
                field: 'status',
                label: 'Status',
                type: 'select',
                placeholder: 'Choose Affiliate Network',
                options: ['active' => 'Active', 'inactive' => 'Inactive'],
                default: 'active',
            ),
        ];
    }

    public function repeaters(): array
    {
        return [
            new Repeater(
                relation: 'affiliateCredentials',
                label: 'Affiliate Credentials',
                fields: [
                    new Field(field: 'label', label: 'Label', type: 'text'),
                    new Field(field: 'key', label: 'Key', type: 'text'),
                    new Field(field: 'value', label: 'Value', type: 'text', required: false),
                ],
                min: 1,
            ),
        ];
    }
}
