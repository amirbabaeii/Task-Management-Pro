<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->string('description', 280)
                ->nullable()
                ->after('name');
        });

        DB::table('boards')->update([
            'description' => 'Empty description',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
