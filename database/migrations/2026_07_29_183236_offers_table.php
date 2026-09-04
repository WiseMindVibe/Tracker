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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('blog_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type')->default('static'); // static | dynamic
            $table->string('merchant_id')->nullable(); // affiliate-specific id (advertiser_id, shop id)
            $table->string('country', 2);
            $table->text('affiliate_link'); // required for static offers
            $table->boolean('is_tester')->default(false);
            $table->string('status')->default('active'); // active | inactive | archived
            $table->unsignedInteger('total_impressions')->default(0);
            $table->unsignedInteger('total_views')->default(0);
            $table->timestamps();
        });

        Schema::create('offers_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->string('article_url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers_articles');
        Schema::dropIfExists('offers');
    }
};
