<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A household is a calendar, so the modules a household has bought into are
     * stored on the calendar itself. Every calendar that already exists is the
     * family's own, so they all start with the medical module switched on.
     */
    public function up(): void
    {
        Schema::table('calendars', function (Blueprint $table) {
            $table->json('modules')->nullable()->after('join_code');
        });

        DB::table('calendars')->update(['modules' => json_encode(['medical' => true])]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendars', function (Blueprint $table) {
            $table->dropColumn('modules');
        });
    }
};
