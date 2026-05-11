<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_members', function (Blueprint $table) {
            $table->json('filter_preferences')->nullable()->after('joined_at');
        });
    }

    public function down(): void
    {
        Schema::table('board_members', function (Blueprint $table) {
            $table->dropColumn('filter_preferences');
        });
    }
};
