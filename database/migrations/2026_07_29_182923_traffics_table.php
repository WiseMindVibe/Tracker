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
        Schema::create('traffic_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('shortcut')->nullable();
        });

        Schema::create('traffic_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traffic_catalog_id')->constrained('traffic_catalog')->restrictOnDelete();
            $table->string('label');
            $table->string('field_key');
        });

        Schema::create('traffic_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('traffic_catalog_id')->constrained('traffic_catalog')->restrictOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'traffic_catalog_id']);
        });

        Schema::create('traffic_accounts_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traffic_account_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('traffic_accounts_credentials');
        Schema::dropIfExists('traffic_accounts');
        Schema::dropIfExists('traffic_field_definitions');
        Schema::dropIfExists('traffic_catalog');
    }
};
