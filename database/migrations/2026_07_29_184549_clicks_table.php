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
            $table->bigIncrements('id');

            $table->uuid('click_id')->unique();

            $table->foreignId('offer_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('traffic_campaign_id')->constrained('campaigns_traffic_ids')->restrictOnDelete();
            $table->string('routed_via')->nullable(); // 'direct' | 'blog'
            $table->string('status')->default('pending'); // pending 'redirected' | 'rejected_no_capacity' | 'rejected_invalid'
            $table->boolean('is_bot')->default(false); // wired up in Phase 3



            $table->string('country', 2)->nullable();
            $table->string('region', 40)->nullable();
            $table->string('language', 40)->nullable();
            $table->string('device', 20)->nullable(); // desktop | mobile | tablet | bot
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


            $table->index(['campaign_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clicks');
    }
};
