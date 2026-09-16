<?php

namespace App\Modules\ModuleList\Offer;

use App\Models\AffiliateAccount;
use App\Models\Blog;
use App\Models\Offer;
use App\Modules\ModuleConfig;
use App\Modules\Support\Actions\CreateAction;
use App\Modules\Support\Actions\EditAction;
use App\Modules\Support\Columns\CountColumn;
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
            'page' => 'Offers',
            'header' => 'Offers',
            'header_s' => 'Offer',
        ];
    }

    public function model(): string
    {
        return Offer::class;
    }

    public function table(): Table
    {
        return new Table(
            columns: [
                new TextColumn('id', 'ID'),
                new TextColumn('is_tester', 'Tester'),
                new TextColumn('name', 'Name'),
                new RelationColumn('affiliateAccount.affiliateCatalog', 'name', 'Affiliate Network'),
                new TextColumn('country', 'Country'),
                new TextColumn('affiliate_link', 'Affiliate Link'),
                new TextColumn('status', 'Status'),
                new TextColumn('merchant_id', 'Merchant ID'),
                new TextColumn('type', 'Type'),
                new RelationColumn('blog', 'domain', 'Blog'),
                new CountColumn('articles', 'Offer Articles'),
                new TextColumn('created_at', 'Created At'),
                new TextColumn('updated_at', 'Updated At'),
            ],
            filters: [
                new SelectFilter('status', ['active', 'inactive']),
            ],
            actions: [
                new CreateAction,
                new EditAction,
            ],
            defaultSort: [
                'column' => 'id',
                'direction' => 'desc',
            ]
        );
    }

    public function fields(): array
    {
        return [
            new Field(
                field: 'affiliate_account_id',
                label: 'Affiliate Network',
                type: 'select',
                placeholder: 'Choose Affiliate Network',
                options: AffiliateAccount::query()
                    ->with('affiliateCatalog')
                    ->get()
                    ->mapWithKeys(fn ($account) => [
                        $account->id => $account->affiliateCatalog?->name ?? "Account #{$account->id}",
                    ])
                    ->toArray(),
            ),
            new Field(
                field: 'blog_id',
                label: 'Blog',
                type: 'select',
                placeholder: 'Choose Blog',
                options: Blog::query()->pluck('domain', 'id')->toArray(),
            ),
            new Field(
                field: 'name',
                label: 'Name',
                type: 'text',
                placeholder: 'Offer - Affiliate GEO'
            ),
            new Field(
                field: 'offer_mode',
                label: 'Offer Mode',
                type: 'select',
                placeholder: 'Offer Mode',
                options: ['static' => 'Static', 'dynamic' => 'Dynamic'],
                default: 'static' // OR by affiliate catalog default offer mode,
            ),
            new Field(
                field: 'merchant_id',
                label: 'Merchant ID', // label is depended on affiliate, yieldkit => Advertiser ID, Oponia => Shop ID
                type: 'text',
            ),
            new Field(
                field: 'country',
                label: 'Country',
                type: 'country',
                placeholder: 'Choose A Country',
                options: collect(config('countries'))->map(fn ($c) => [
                    'value' => $c['code'],
                    'label' => $c['name'],
                    'aliases' => $c['aliases'] ?? [],
                ])->values()->toArray(),
            ),
            new Field(
                field: 'affiliate_link',
                label: 'Affiliate Link',
                type: 'url',
            ),
            new Field(
                field: 'is_tester',
                label: 'Tester',
                type: 'bool', // Options to toggle for tester or not, 1/0
            ),
            new Field(
                field: 'status',
                label: 'Status',
                type: 'select',
                placeholder: '',
                options: ['active' => 'Active', 'inactive' => 'Inactive'],
                default: 'active',
            ),
        ];
    }

    public function repeaters(): array
    {
        return [
            new Repeater(
                relation: 'articles',
                label: 'Articles URLs',
                fields: [
                    new Field(field: 'article_url', label: 'Article URL', type: 'url', placeholder: 'https://...', options: [], default: null, required: false),
                ],
                min: 0,
            ),
        ];
    }
}
