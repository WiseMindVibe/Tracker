<?php

namespace App\Modules\ModuleList\AffiliateCatalog;

use App\Models\AffiliateCatalog;
use App\Modules\ModuleConfig;
use App\Modules\Support\Actions\CreateAction;
use App\Modules\Support\Actions\ViewAction;
use App\Modules\Support\Columns\PercentageColumn;
use App\Modules\Support\Columns\RelationColumn;
use App\Modules\Support\Columns\TextColumn;
use App\Modules\Support\Field\Field;
use App\Modules\Support\Filters\SelectFilter;
use App\Modules\Support\Table\Table;

class Config extends ModuleConfig
{
    public function titles(): array
    {
        return [
            'page' => 'Affiliate Catalogs',
            'header' => 'Affiliate Catalogs',
            'header_s' => 'Affiliate Catalog'
        ];
    }

    public function model(): string
    {
        return AffiliateCatalog::class;
    }

    public function table(): Table
    {
        return new Table(
            columns: [
                new TextColumn('id', 'ID'),
                new TextColumn('name', 'Affiliate Network'),
                new TextColumn('affiliate_token', 'Affiliate Token'),
                new TextColumn('offer_mode', 'Offer Mode'),
                new TextColumn('commission_mode', 'Commission Mode'),
                new TextColumn('merchant_id_label', 'Merchant ID Label'),
                new TextColumn('blog_redirect_rate', 'Blog Reidrect Rate'),


            ],
            filters: [],
            actions: [
                new ViewAction(),
            ],
            defaultSort: [
                'column' => 'id',
                'direction' => 'asc'
            ]
        );
    }

    public function fields(): array
    {
        return [
            new Field(
                field: 'id',
                label: 'ID',
                type: 'text',
            ),
            new Field(
                field: 'name',
                label: 'Name',
                type: 'text',
            ),
            new Field(
                field: 'slug',
                label: 'Slug',
                type: 'text',
                required: false,
                disabled: true,
            ),
            new Field(
                field: 'affiliate_token',
                label: 'Affiliate Token',
                type: 'text',
            ),
            new Field(
                field: 'offer_mode',
                label: 'Offer Mode',
                type: 'text',
            ),
            new Field(
                field: 'commission_mode',
                label: 'Commission Mode',
                type: 'text',
            ),
            new Field(
                field: 'merchant_id_label',
                label: 'Merchant ID Label',
                type: 'text',
            ),
            new Field(
                field: 'blog_redirect_rate',
                label: 'Blog Redirect Rate',
                type: 'text',
            ),
        ];
    }
}
