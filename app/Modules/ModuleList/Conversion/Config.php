<?php

namespace App\Modules\ModuleList\Conversion;

use App\Models\Conversion;
use App\Modules\ModuleConfig;
use App\Modules\Support\Columns\MoneyColumn;
use App\Modules\Support\Columns\RelationColumn;
use App\Modules\Support\Columns\TextColumn;
use App\Modules\Support\Filters\SelectFilter;
use App\Modules\Support\Table\Table;

class Config extends ModuleConfig
{
    public function title(): string
    {
        return 'Conversions';
    }

    public function model(): string
    {
        return Conversion::class;
    }

    public function table(): Table
    {
        return new Table(
            columns: [
                new TextColumn('id', 'ID'),
                new RelationColumn('conversionevent.click', 'click_id', 'Click ID'),
                new RelationColumn('conversionevent', 'commission', 'Commission'),
                new RelationColumn('conversionevent', 'status', 'Status'),
                new TextColumn('conversion_id', 'Conversion ID'),
                new TextColumn('created_at', 'Created At'),
                new TextColumn('updated_at', 'Updated At'),

            ],
            filters: [
                new SelectFilter('status', ['open', 'confirmed', 'rejected', 'paid']),
            ],
            actions: [
            ]
        );
    }
}
