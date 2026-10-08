<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payment_method',
        'bank',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
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

    public function scopeBank(
        Builder $query
    ): Builder {
        return $query->where('payment_method', 'bank');
    }

    public function scopeMpesa(
        Builder $query
    ): Builder {
        return $query->where('payment_method', 'mpesa');
    }

    public function scopeEcocash(
        Builder $query
    ): Builder {
        return $query->where('payment_method', 'ecocash');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

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
}