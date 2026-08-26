<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        //Yieldkit
        //Postback
        //{EVENT_ID}=624324 The event id, this value is unique.
        //{ADVERTISER_ID}=bsdv52 The advertiser id.
        //{COMMISSION_ID}=bvf234 The ID of commission, this value can occur multiple times if a commission changes its value or gets cancelled.
        //{COMMISSION}=10 The commission amount. It is positive for a new commission and negative if the commission was cancelled.
        //{SALES_DATE}=2026-05-29T17:28:03Z The date when the sale happened.
        //{MODIFIED_DATE}=2026-07-29T17:28:03Z The date when the event happened.
        //{SUB_ID}=35234das The sub id which you might have specified via yk_tag.
        //{SALES_AMOUNT}=100 The total amount of the purchase.
        //{EVENT_TYPE}=NEW Can be "NEW" or "UPDATE". Indicates whether it is a new commission in our system or if a commission is updated.
        //{STATE}=OPEN The commission status, can be "OPEN", "CONFIRMED", "REJECTED", "DELAYED".
        //Our S2S Postback only fires commission data in EUR currency (this is why we do not provide a currency parameter there).

        //API
        //"id": "8a1dc5eaec9f48c8ad89dc14dcb33dc1", (Commission_id -> unique)
        //"advertiserName": "Cosm", Name
        //"commission": 1.7121, commission
        //"state": "CONFIRMED", state
        //"date": "2026-07-23T01:12:39Z", created_date
        //"ykTag": "cid57a06a7b7587ff.46203397", click_id
        //"advertiserId": "685aca1879524e50861a06d0bf7cbeef", advertiser_id
        //"modified_date": "2026-07-29T04:04:24Z", updated_date
        //"orderId": "$oid:6a616a3dae89027d6b11d3e1", IGNORE - NULL
        //"currency": "EUR", IGNORE - Mostly EUR
        //"amount": 17.56, Advertiser sale
        //"commissionType": "SALE", //type, sale, lead etc
        //"payoutId": null, //commission => paid => ID
        //"clickCountryCode": "US", => GEO code
        //"siteId": "f6f98f67135740488354d7bfb92369cf" //site id

        //Oponia
        //Postback
        //{EVENT_ID}=5123 - Unique ID of the transaction, 64 characters
        //{ADVERTISER_ID}=gsdf24 - Id of the merchant, see Merchant Api
        //{COMMISSION_ID}=5sfa3 - Id of the commission, 64 characters
        //{COMMISSION}=5 - Value of the commission, decimal number with separator "."
        //{MODIFIED_DATE} - Change date of the status change in the format "2024-08-12T15:52:01+00:00"
        //{SUB_ID}=bdv34t - Publisher PlacementId 1
        //{SUB_ID_2}=null - Publisher PlacementId 2
        //{EVENT_TYPE}=NEW - "NEW" or "UPDATE"
        //{STATE}=open - "open", "confirmed", "rejected", "paid"
        //{CLICK_DATE} - date of the user click in the format "2024-08-12T15:52:01+00:00"
        //{CURRENCY} - "EUR". The current data in the endpoint is always in EUR
        //{MARKET}=GB - Name of the market in which the click occurred in the format ISO 3166-1 alpha-2
        //{MERCHANT_NAME} - name of the merchant

        //API
        //"commissionId": "8e6271446d86792638070b717ccb7e4f9a5d31ac4b23c09d45b56963ec930f6e",
        //"clickId": "d2s5n8adfyk2omq4yyoj7zf8",
        //"clickDate": "2026-07-24T22:35:33+00:00",
        //"modifiedDate": "2026-07-29T04:24:52+00:00",
        //"status": "open",
        //"revenue": 3.256,
        //"merchant": "europcar.co.uk",
        //"merchantId": "6d331777a4ca25fa",
        //"placementId": "cidde19111d52e325.16067203",
        //"placementId2": null

        Schema::create('conversions_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->foreignId('click_id')->constrained()->restrictOnDelete();
            $table->string('commission_id');
            $table->decimal('commission', 10, 5);
            $table->string('status');

            $table->timestamps();
        });

        Schema::create('conversions', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->foreignId('conversion_event_id')->constrained('conversions_events')->restrictOnDelete();
            $table->string('conversion_id')->unique();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversions');
        Schema::dropIfExists('conversions_events');
    }
};
