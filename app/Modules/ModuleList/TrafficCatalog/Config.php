<?php

namespace App\Modules\ModuleList\TrafficCatalog;

use App\Models\TrafficCatalog;
use App\Modules\ModuleConfig;
use App\Modules\Support\Actions\ViewAction;
use App\Modules\Support\Columns\TextColumn;
use App\Modules\Support\Field\Field;
use App\Modules\Support\Table\Table;

class Config extends ModuleConfig
{
    public function titles(): array
    {
        return [
            'page' => 'Traffic Catalogs',
            'header' => 'Traffic Catalogs',
            'header_s' => 'Traffic Catalog',
        ];
    }

    public function model(): string
    {
        return TrafficCatalog::class;
    }

    public function table(): Table
    {
        return new Table(
            columns: [
                new TextColumn('id', 'ID'),
                new TextColumn('name', 'Traffic Source'),
                new TextColumn('shortcut', 'Shortcut'),
            ],
            filters: [],
            actions: [
                new ViewAction,
            ]
        );
    }

    public function fields(): array
    {
        return [
            new Field(
                field: 'id',
                label: 'ID'
            ),
            new Field(
                field: 'name',
                label: 'Name'
            ),
            new Field(
                field: 'slug',
                label: 'Slug',
                type: 'text',
                required: false,
                disabled: true,
            ),
        ];
    }
}
