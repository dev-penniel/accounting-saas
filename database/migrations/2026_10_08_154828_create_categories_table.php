<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            $table->enum('type', [
                'income',
                'expense',
            ]);

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->boolean('is_default')
                ->default(false);

            $table->boolean('is_hidden')
                ->default(false);

            $table->timestamps();

            $table->index([
                'user_id',
                'type',
            ]);

            $table->index([
                'user_id',
                'parent_id',
            ]);

            $table->index([
                'user_id',
                'is_hidden',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};