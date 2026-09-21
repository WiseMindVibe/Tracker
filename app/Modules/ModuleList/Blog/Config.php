<?php

namespace App\Modules\ModuleList\Blog;

use App\Models\Blog;
use App\Models\Company;
use App\Modules\ModuleConfig;
use App\Modules\Support\Actions\CreateAction;
use App\Modules\Support\Actions\EditAction;
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
        return[
            'page' => 'Blogs',
            'header' => 'Blogs',
            'header_s' => 'Blog'
        ];
    }

    public function model(): string
    {
        return Blog::class;
    }

    public function table(): Table
    {
        return new Table(
            columns: [
                new TextColumn('id', 'ID'),
                new RelationColumn('company', 'name', 'Company Owned'),
                new TextColumn('domain', 'Domain'),
                new TextColumn('main_geo', 'Main GEO'),
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
                'direction' => 'asc'
            ]
        );
    }

    public function fields(): array
    {
        return [
            new Field(
                field: 'company_id',
                label: 'Company',
                type: 'select',
                placeholder: 'Choose A Company',
                options: Company::query()->pluck('name', 'id')->toArray(),
            ),
            new Field(
                field: 'domain',
                label: 'Domain',
                type: 'url',
                ),
            new Field(
                field: 'main_geo',
                label: 'Country',
                type: 'country',
                options: collect(config('countries'))->map(fn($c) => [
                    'value' => $c['code'],
                    'label' => $c['name'],
                    'aliases' => $c['aliases'] ?? [],
                ])->values()->toArray(),
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
                relation: 'buffers',
                label: 'Buffer URLs',
                fields: [
                    new Field(field: 'buffer_url', label: 'Buffer URL', type: 'url', placeholder: 'https://...'),
                ],
                min: 1,
            ),
        ];
    }
}
