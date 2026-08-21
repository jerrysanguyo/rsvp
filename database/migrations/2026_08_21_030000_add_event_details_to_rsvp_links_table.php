<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rsvp_links', function (Blueprint $table) {
            $table->date('event_date')->nullable()->after('title');
            $table->string('event_time', 80)->nullable()->after('event_date');
            $table->string('venue', 160)->nullable()->after('event_time');
            $table->string('venue_map_url', 2048)->nullable()->after('venue');
        });

        DB::table('rsvp_links')->whereNull('event_date')->update([
            'event_date' => '2026-12-27',
            'event_time' => '7:00–9:00 PM',
            'venue' => 'Jollibee Global City',
        ]);
    }

    public function down(): void
    {
        Schema::table('rsvp_links', function (Blueprint $table) {
            $table->dropColumn(['event_date', 'event_time', 'venue', 'venue_map_url']);
        });
    }
};
