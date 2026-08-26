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
        Schema::create('companies', function (Blueprint $table){
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            //Proxies
            $table->string('proxy_host')->nullable();
            $table->unsignedSmallInteger('proxy_port')->nullable();
            $table->string('proxy_username')->nullable();
            $table->string('proxy_password')->nullable();

            $table->string('status');
            $table->timestamps();
        });

        Schema::create('user_companies', function ( Blueprint $table ){
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_companies');
        Schema::dropIfExists('companies');
    }
};
