<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('name');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('geo_city')->nullable()->after('longitude');
            $table->string('geo_country')->nullable()->after('geo_city');
            $table->text('geo_display_name')->nullable()->after('geo_country');
        });
    }

    public function down()
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'geo_city', 'geo_country', 'geo_display_name']);
        });
    }
};