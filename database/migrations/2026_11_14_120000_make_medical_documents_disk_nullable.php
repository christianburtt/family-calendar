<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Laravel Cloud attaches a private bucket as the environment's default disk
     * rather than one with a fixed name, so a document has to be able to say
     * "wherever this environment's default is" instead of naming a disk that
     * only exists on one host.
     */
    public function up(): void
    {
        Schema::table('medical_documents', function (Blueprint $table) {
            $table->string('disk')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_documents', function (Blueprint $table) {
            $table->string('disk')->nullable(false)->change();
        });
    }
};
