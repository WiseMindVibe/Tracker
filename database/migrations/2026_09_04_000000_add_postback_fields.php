<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversions_events', function (Blueprint $table): void {
            $table->foreignId('affiliate_catalog_id')->after('click_id')->constrained('affiliate_catalog')->restrictOnDelete();
            $table->string('external_event_id')->after('commission_id');
            $table->string('event_type')->after('status');
            $table->string('currency', 3)->default('EUR')->after('event_type');
            $table->timestamp('event_occurred_at')->nullable()->after('currency');

            $table->unique(['affiliate_catalog_id', 'external_event_id']);
        });

        Schema::table('conversions', function (Blueprint $table): void {
            $table->string('commission_id')->nullable()->after('conversion_id');
            $table->decimal('commission', 10, 5)->nullable()->after('commission_id');
            $table->string('status')->nullable()->after('commission');
            $table->string('currency', 3)->nullable()->after('status');
        });
    }

    public function down(): void
    {

        Schema::table('conversions', function (Blueprint $table): void {
            $table->dropColumn(['commission_id', 'commission', 'status', 'currency']);
        });
        Schema::table('conversions_events', function (Blueprint $table): void {
            $table->dropUnique(['affiliate_catalog_id', 'external_event_id']);
            $table->dropForeign(['affiliate_catalog_id']);
            $table->dropColumn(['affiliate_catalog_id', 'external_event_id', 'event_type', 'currency', 'event_occurred_at']);
        });
    }
};
