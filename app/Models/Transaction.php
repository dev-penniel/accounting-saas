<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'date',
        'description',
        'category_id',
        'amount',
        'counterparty_name',
        'payment_method',
        'bank',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    // payments
    public static function paymentMethods(): array
    {
        return [
            'bank' => 'Bank Account',
            'mpesa' => 'M-Pesa',
            'ecocash' => 'EcoCash',
        ];
    }

    // Banks
    public static function banks(): array
    {
        return [
            'standard_lesotho_bank' => 'Standard Lesotho Bank',
            'first_national_bank_lesotho' => 'First National Bank Lesotho',
            'nedbank_lesotho' => 'Nedbank Lesotho',
            'postbank_lesotho' => 'PostBank Lesotho',
            'other' => 'Other',
        ];
    }

    // transaction calculating logic
    public static function balanceFor(
    int $userId,
    string $paymentMethod,
    ?string $bank = null
    ): float {
        $query = static::query()
            ->where('user_id', $userId)
            ->where('payment_method', $paymentMethod);

        if ($paymentMethod === 'bank') {
            $query->where('bank', $bank);
        }

        $income = (clone $query)
            ->where('type', 'income')
            ->sum('amount');

        $expenses = (clone $query)
            ->where('type', 'expense')
            ->sum('amount');

        return (float) $income - (float) $expenses;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForUser(
        Builder $query,
        int $userId
    ): Builder {
        return $query->where('user_id', $userId);
    }

    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('type', 'income');
    }

    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('type', 'expense');
    }

    public function scopeBetween(
        Builder $query,
        $from,
        $to
    ): Builder {
        return $query->whereBetween('date', [$from, $to]);
    }

    public function scopePaymentMethod(
        Builder $query,
        string $method
    ): Builder {
        return $query->where('payment_method', $method);
    }

    public function scopeBank(
        Builder $query,
        string $bank
    ): Builder {
        return $query->where('bank', $bank);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isIncome(): bool
    {
        return $this->type === 'income';
    }

    public function isExpense(): bool
    {
        return $this->type === 'expense';
    }

    public function isBank(): bool
    {
        return $this->payment_method === 'bank';
    }

    public function isMpesa(): bool
    {
        return $this->payment_method === 'mpesa';
    }

    public function isEcocash(): bool
    {
        return $this->payment_method === 'ecocash';
    }

    public function signedAmount(): float
    {
        return $this->isExpense()
            ? -abs((float) $this->amount)
            : abs((float) $this->amount);
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'bank' => 'Bank Account',
            'mpesa' => 'M-Pesa',
            'ecocash' => 'EcoCash',
            default => ucfirst($this->payment_method),
        };
    }

    public function bankLabel(): ?string
    {
        return match ($this->bank) {
            'standard_lesotho_bank' => 'Standard Lesotho Bank',
            'first_national_bank_lesotho' => 'First National Bank Lesotho',
            'nedbank_lesotho' => 'Nedbank Lesotho',
            'postbank_lesotho' => 'PostBank Lesotho',
            'other' => 'Other',
            default => null,
        };
    }

    public function formattedAmount(): string
    {
        return 'M' . number_format(
            (float) $this->amount,
            2
        );
    }

    public function displayAmount(): string
    {
        $prefix = $this->isExpense() ? '-' : '+';

        return $prefix . 'M' . number_format(
            (float) $this->amount,
            2
        );
    }
}