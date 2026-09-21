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
        Schema::create('clicks', function (Blueprint $table) {
            $table->id();

            $table->string('sub_id')->nullable();
            $table->uuid('click_id')->unique();

            $table->foreignId('offer_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->string('traffic_campaign_id')->nullable();
            $table->string('status')->default('pending');

            $table->string('country', 2)->nullable();
            $table->string('region', 40)->nullable();
            $table->string('language', 40)->nullable();
            $table->string('device', 20)->nullable(); // desktop | mobile | tablet
            $table->string('os', 40)->nullable();
            $table->string('os_version', 40)->nullable();
            $table->string('browser', 40)->nullable();
            $table->string('browser_version', 40)->nullable();
            $table->string('connection_type', 40)->nullable();
            $table->string('isp', 40)->nullable();
            $table->string('carrier', 40)->nullable();
            $table->string('zoneid', 40)->nullable();
            $table->string('subzone_id', 40)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('user_activity')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->decimal('cost', 10, 5)->default(0);
            $table->timestamps();

            // clicks table
            $table->index(['offer_id', 'created_at']);
            $table->index(['campaign_id', 'created_at']); // already exists
            $table->index(['created_at']);  // useful for top-level reports
            $table->index(['created_at', 'offer_id'], 'clicks_reporting_created_offer_index');
            $table->index(['offer_id', 'created_at', 'campaign_id'], 'clicks_reporting_offer_date_campaign_index');
            $table->index(['offer_id', 'campaign_id', 'created_at', 'os'], 'clicks_reporting_offer_campaign_date_os_index');
            $table->index(['offer_id', 'campaign_id', 'os', 'created_at', 'browser'], 'clicks_reporting_offer_campaign_os_date_browser_index');
        });

        Schema::create('clicks_redirections', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('click_id');
            $table->string('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clicks_redirections');
        Schema::dropIfExists('clicks');
    }
};
