<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('default_key')->nullable()->after('is_default');

            $table->unique(
                ['user_id', 'default_key'],
                'categories_user_default_key_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_user_default_key_unique');
            $table->dropColumn('default_key');
        });
    }
};
