<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('type', [
                'income',
                'expense',
            ]);

            $table->date('date');

            $table->string('description');

            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->decimal('amount', 12, 2);

            $table->string('counterparty_name')->nullable();

            $table->enum('payment_method', [
                'bank',
                'mpesa',
                'ecocash',
            ]);

            $table->enum('bank', [
                'standard_lesotho_bank',
                'first_national_bank_lesotho',
                'nedbank_lesotho',
                'postbank_lesotho',
                'other',
            ])->nullable();

            $table->string('reference')->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'date',
            ]);

            $table->index([
                'user_id',
                'type',
            ]);

            $table->index([
                'user_id',
                'payment_method',
            ]);

            $table->index([
                'user_id',
                'category_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};