<?php

namespace App\Services\Postbacks\Adapters;

use App\Services\Postbacks\PostbackAdapter;
use App\Services\Postbacks\PostbackData;
use Illuminate\Http\Request;

class YieldKitPostbackAdapter implements PostbackAdapter
{
    public function parse(Request $request): PostbackData
    {
        return new PostbackData(

            clickId: $request->input('SUB_ID'),

            commissionId: $request->input('COMMISSION_ID'),

            commission: $request->input('COMMISSION'),

            status: strtoupper(
                $request->input('STATE', 'OPEN')
            ),

            currency: 'EUR',

            eventId: $request->input('EVENT_ID'),
            eventType: $request->input('EVENT_TYPE'),

            advertiserId: $request->input('ADVERTISER_ID'),

            saleDate: $request->input('SALES_DATE'),

            modifiedDate: $request->input('MODIFIED_DATE'),

            advertiserSaleAmount: $request->input('SALES_AMOUNT'),






        );
    }
}
