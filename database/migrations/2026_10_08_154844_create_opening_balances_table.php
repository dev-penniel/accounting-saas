<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

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

            $table->decimal('amount', 12, 2)
                ->default(0);

            $table->timestamps();

            $table->index([
                'user_id',
                'payment_method',
            ]);

            $table->index([
                'user_id',
                'bank',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balances');
    }
};