<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->uuid('deviceId')->index();
            $table->uuid('siteId')->index();
            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedTinyInteger('pingEnabled')->default(0);
            $table->text('ipAddress');
            $table->json('cidrIpAddress');
            $table->text('hostname');
            $table->text('macAddress')->nullable();
            $table->text('modelName');
            $table->text('vendorName');
            $table->text('deviceRole');
            $table->timestamp('query_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
