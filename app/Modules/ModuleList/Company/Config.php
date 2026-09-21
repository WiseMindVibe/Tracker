<?php

namespace App\Modules\ModuleList\Company;

use App\Models\Company;
use App\Modules\ModuleConfig;
use App\Modules\Support\Actions\ViewAction;
use App\Modules\Support\Columns\TextColumn;
use App\Modules\Support\Field\Field;
use App\Modules\Support\Filters\SelectFilter;
use App\Modules\Support\Table\Table;

class Config extends ModuleConfig
{
    public function titles(): array
    {
        return [
            'page' => 'Companies',
            'header' => 'Companies',
            'header_s' => 'Company'
        ];
    }

    public function header(): string
    {
        return 'Companies List';
    }

    public function model(): string
    {
        return Company::class;
    }

    public function table(): Table
    {
        return new Table(
            columns: [
                new TextColumn('id', 'ID'),
                new TextColumn('name', 'Company'),
                new TextColumn('status', 'Status'),
                new TextColumn('created_at', 'Created At'),
                new TextColumn('updated_at', 'Updated At'),

            ],
            filters: [
                new SelectFilter('status', ['active', 'inactive']),
            ],
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
                field: 'name',
                label: 'Name',
                type: 'text',
            ),
            new Field(
                field: 'slug',
                label: 'Slug',
                type: 'text',
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
}
