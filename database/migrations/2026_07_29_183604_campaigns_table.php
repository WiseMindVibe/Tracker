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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('traffic_account_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('country', 2)->nullable();
            $table->boolean('is_tester')->default(false);
            $table->string('fallback_url')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('impressions')->default(0);
            $table->timestamps();
        });

        Schema::create('campaigns_traffic_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->string('traffic_campaign_id')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('campaigns_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('offer_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('current_impressions')->default(0);
            $table->unsignedBigInteger('current_views')->default(0);
            $table->unsignedBigInteger('cap_views')->default(0);
            $table->unsignedBigInteger('total_views')->default(0);
            $table->timestamps();

            $table->unique(['campaign_id', 'offer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns_offers');
        Schema::dropIfExists('campaigns_traffic_ids');
        Schema::dropIfExists('campaigns');
    }
};
