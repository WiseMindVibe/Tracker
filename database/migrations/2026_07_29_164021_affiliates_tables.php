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
        Schema::create('affiliate_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('shortcut')->nullable();
            $table->string('affiliate_token');
            $table->string('offer_mode')->default('static'); // static | dynamic
            $table->string('commission_mode')->default('delta'); // delta | absolute
            $table->string('merchant_id_label')->nullable(); // e.g. "advertiser_id", "Shop ID"
            $table->integer('blog_redirect_rate');
        });


        Schema::create('affiliate_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_catalog_id')->constrained('affiliate_catalog')->restrictOnDelete();
            $table->string('label');
            $table->string('field_key');
        });

        Schema::create('affiliate_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('affiliate_catalog_id')->constrained('affiliate_catalog')->restrictOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'affiliate_catalog_id']);
        });

        Schema::create('affiliate_accounts_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_account_id')->constrained('affiliate_accounts', 'id')->restrictOnDelete();
            $table->string('label')->nullable();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_accounts_credentials');
        Schema::dropIfExists('affiliate_accounts');
        Schema::dropIfExists('affiliate_field_definitions');
        Schema::dropIfExists('affiliate_catalog');
    }
};
