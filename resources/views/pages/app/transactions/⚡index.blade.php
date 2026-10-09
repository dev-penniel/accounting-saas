<?php

use App\Models\Transaction;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts::app.frontend')]
class extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public string $type = 'expense';
    public string $date = '';
    public string $description = '';
    public string $category_id = '';
    public string $amount = '';
    public string $counterparty_name = '';
    public string $payment_method = 'bank';
    public string $bank = '';
    public string $reference = '';

    public string $search = '';
    public string $typeFilter = 'all';
    public string $paymentMethodFilter = 'all';
    public string $fromDate = '';
    public string $toDate = '';

    public function mount(): void
    {
        $this->date = now()->toDateString();
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();
    }

    public function openForm(string $type): void
    {
        abort_unless(in_array($type, ['income', 'expense'], true), 404);

        $this->resetValidation();

        $this->type = $type;
        $this->date = now()->toDateString();
        $this->category_id = '';
        $this->description = '';
        $this->amount = '';
        $this->counterparty_name = '';
        $this->payment_method = 'bank';
        $this->bank = '';
        $this->reference = '';

        $this->dispatch('modal-show', name: 'transaction-form');
    }

    public function closeForm(): void
    {
        $this->dispatch('modal-close', name: 'transaction-form');
        $this->resetValidation();
    }

    public function updatedType(): void
    {
        $this->category_id = '';
    }

    public function updatedPaymentMethod(): void
    {
        if ($this->payment_method !== 'bank') {
            $this->bank = '';
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPaymentMethodFilter(): void
    {
        $this->resetPage();
    }

    public function updatedFromDate(): void
    {
        $this->resetPage();
    }

    public function updatedToDate(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function categories()
    {
        return auth()->user()->categories()
            ->where('type', $this->type)
            ->where('is_hidden', false)
            ->when(
                $this->type === 'expense',
                fn ($query) => $query->whereNotNull('parent_id'),
                fn ($query) => $query->whereNull('parent_id')
            )
            ->orderBy('name')
            ->get();
    }

    // private function periodQuery(): Builder
    // {
    //     return auth()->user()->transactions()
    //         ->when($this->fromDate, fn ($query) =>
    //             $query->whereDate('date', '>=', $this->fromDate)
    //         )
    //         ->when($this->toDate, fn ($query) =>
    //             $query->whereDate('date', '<=', $this->toDate)
    //         );
    // }

    #[Computed]
    public function summary(): array
    {
        $totals = auth()->user()->transactions()
            ->when($this->fromDate, fn ($query) =>
                $query->whereDate('date', '>=', $this->fromDate)
            )
            ->when($this->toDate, fn ($query) =>
                $query->whereDate('date', '<=', $this->toDate)
            )
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expenses,
                COUNT(*) as transaction_count
            ")
            ->first();

        $income = (float) $totals->income;
        $expenses = (float) $totals->expenses;

        return [
            'income' => $income,
            'expenses' => $expenses,
            'net' => $income - $expenses,
            'count' => (int) $totals->transaction_count,
        ];
    }

    #[Computed]
    public function transactions()
    {
        return auth()->user()->transactions()
            ->with('category')
            ->when($this->fromDate, fn ($query) =>
                $query->whereDate('date', '>=', $this->fromDate)
            )
            ->when($this->toDate, fn ($query) =>
                $query->whereDate('date', '<=', $this->toDate)
            )
            ->when($this->typeFilter !== 'all', fn ($query) =>
                $query->where('type', $this->typeFilter)
            )
            ->when($this->paymentMethodFilter !== 'all', fn ($query) =>
                $query->where('payment_method', $this->paymentMethodFilter)
            )
            ->when(trim($this->search) !== '', function ($query) {
                $search = '%' . trim($this->search) . '%';

                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', $search)
                        ->orWhere('counterparty_name', 'like', $search)
                        ->orWhere('reference', 'like', $search)
                        ->orWhereHas('category', fn ($categoryQuery) =>
                            $categoryQuery->where('name', 'like', $search)
                        );
                });
            })
            ->latest('date')
            ->latest('id')
            ->paginate(10);
    }

    public function save(): void
    {
        // Test
        // $validated = $this->validate([
        //     'type' => ['required', Rule::in(['income', 'expense'])],
        //     'date' => ['required', 'date'],
        //     'description' => ['required', 'string', 'max:255'],
        //     'category_id' => ['required', 'integer', 'exists:categories,id'],
        //     'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
        //     'counterparty_name' => ['nullable', 'string', 'max:255'],
        //     'payment_method' => ['required', Rule::in(['bank', 'mpesa', 'ecocash'])],
        //     'bank' => ['nullable', 'string'],
        //     'reference' => ['nullable', 'string', 'max:255'],
        // ]);

        // production

        $validated = $this->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],

            'category_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $categoryExists = auth()->user()->categories()
                        ->whereKey($value)
                        ->where('type', $this->type)
                        ->where('is_hidden', false)
                        ->exists();

                    if (! $categoryExists) {
                        $fail('Please select a valid category for this transaction.');
                    }
                },
            ],

            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'counterparty_name' => ['nullable', 'string', 'max:255'],

            'payment_method' => [
                'required',
                Rule::in(['bank', 'mpesa', 'ecocash']),
            ],

            'bank' => [
                Rule::requiredIf($this->payment_method === 'bank'),
                'nullable',
                Rule::in([
                    'standard_lesotho_bank',
                    'first_national_bank_lesotho',
                    'nedbank_lesotho',
                    'postbank_lesotho',
                    'other',
                ]),
            ],

            'reference' => ['nullable', 'string', 'max:255'],
        ]);
    
        $validated['bank'] = $validated['payment_method'] === 'bank'
            ? $validated['bank']
            : null;

        
        
        auth()->user()->transactions()->create($validated);

        $this->showForm = false;
        $this->resetPage();

        $this->reset([
            'description',
            'category_id',
            'amount',
            'counterparty_name',
            'bank',
            'reference',
        ]);

        $this->type = 'expense';
        $this->payment_method = 'bank';
        $this->date = now()->toDateString();

        $this->resetValidation();

        session()->flash('success', 'Transaction saved successfully.');
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6 pb-10">

    {{-- Page heading and primary actions --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                Transactions
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Track your business income, expenses and money movement.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button
                wire:click="openForm('income')"
                variant="primary"
                icon="plus"
            >
                Add income
            </flux:button>

            <flux:button
                wire:click="openForm('expense')"
                variant="filled"
                icon="minus"
            >
                Add expense
            </flux:button>

            
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-lg border w-full mt-5 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif

    {{-- Period and summary --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <flux:label>From</flux:label>
                <flux:input type="date" wire:model.live="fromDate" />
            </div>

            <div class="flex-1">
                <flux:label>To</flux:label>
                <flux:input type="date" wire:model.live="toDate" />
            </div>

            <flux:button
                variant="ghost"
                wire:click="$set('fromDate', '{{ now()->startOfMonth()->toDateString() }}'); $set('toDate', '{{ now()->toDateString() }}')"
            >
                This month
            </flux:button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">Income received</p>
                <div class="rounded-lg bg-green-50 p-2 text-green-700 dark:bg-green-950 dark:text-green-300">
                    <flux:icon name="arrow-trending-up" class="size-5" />
                </div>
            </div>
            <p class="mt-3 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">
                M {{ number_format($this->summary['income'], 2) }}
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">Expenses paid</p>
                <div class="rounded-lg bg-red-50 p-2 text-red-700 dark:bg-red-950 dark:text-red-300">
                    <flux:icon name="arrow-trending-down" class="size-5" />
                </div>
            </div>
            <p class="mt-3 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">
                M {{ number_format($this->summary['expenses'], 2) }}
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm text-gray-500">Net movement</p>
            <p class="mt-3 text-2xl font-semibold tabular-nums {{ $this->summary['net'] < 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">
                {{ $this->summary['net'] < 0 ? '-' : '' }}M {{ number_format(abs($this->summary['net']), 2) }}
            </p>
            <p class="mt-1 text-xs text-gray-500">Income minus expenses for this period</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm text-gray-500">Transactions recorded</p>
            <p class="mt-3 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">
                {{ number_format($this->summary['count']) }}
            </p>
            <p class="mt-1 text-xs text-gray-500">For the selected period</p>
        </div>
    </div>

{{-- Transaction modal --}}
<flux:modal name="transaction-form" class="w-full max-w-2xl">
    <div class="space-y-6">
        {{-- Modal heading --}}
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">
                    {{ $type === 'income' ? 'Record income' : 'Record expense' }}
                </flux:heading>

                <flux:subheading>
                    Record your business transaction accurately.
                </flux:subheading>


                @if (session('success'))
                    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        {{ session('success') }}
                    </div>
                @endif

            </div>
        </div>

        <form wire:submit="save" class="space-y-5">
            {{-- Transaction type --}}
            <div>
                <flux:label>Transaction type</flux:label>

                <flux:radio.group wire:model.live="type">
                    <flux:radio value="income" label="Income" />
                    <flux:radio value="expense" label="Expense" />
                </flux:radio.group>

                @error('type')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            {{-- Date and amount --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:label>Date</flux:label>
                    <flux:input type="date" wire:model="date" />

                    @error('date')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>

                <div>
                    <flux:label>Amount (M)</flux:label>
                    <flux:input
                        type="number"
                        min="0.01"
                        step="0.01"
                        wire:model="amount"
                        placeholder="0.00"
                    />

                    @error('amount')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
            </div>

            {{-- Description --}}
            <div>
                <flux:label>Description</flux:label>
                <flux:input
                    wire:model="description"
                    placeholder="e.g. Website development payment"
                />

                @error('description')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            {{-- Category --}}
            <div>
                <flux:label>Category</flux:label>

                <flux:select
                    wire:model="category_id"
                    placeholder="Select a category"
                >
                    @foreach ($this->categories as $category)
                        <flux:select.option
                            value="{{ $category->id }}"
                        >
                            {{ $category->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                @error('category_id')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            {{-- Customer / supplier --}}
            <div>
                <flux:label>Customer / supplier</flux:label>
                <flux:input
                    wire:model="counterparty_name"
                    placeholder="Optional"
                />

                @error('counterparty_name')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            {{-- Payment method --}}
            <div>
                <flux:label>Payment method</flux:label>

                <flux:select wire:model.live="payment_method">
                    <flux:select.option value="bank">
                        Bank account
                    </flux:select.option>
                    <flux:select.option value="mpesa">
                        M-Pesa
                    </flux:select.option>
                    <flux:select.option value="ecocash">
                        EcoCash
                    </flux:select.option>
                </flux:select>

                @error('payment_method')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            {{-- Bank details only when bank is selected --}}
            @if ($payment_method === 'bank')
                <div>
                    <flux:label>Bank</flux:label>

                    <flux:select
                        wire:model="bank"
                        placeholder="Select a bank"
                    >
                        <flux:select.option value="standard_lesotho_bank">
                            Standard Lesotho Bank
                        </flux:select.option>
                        <flux:select.option value="first_national_bank_lesotho">
                            First National Bank Lesotho
                        </flux:select.option>
                        <flux:select.option value="nedbank_lesotho">
                            Nedbank Lesotho
                        </flux:select.option>
                        <flux:select.option value="postbank_lesotho">
                            PostBank Lesotho
                        </flux:select.option>
                        <flux:select.option value="other">
                            Other
                        </flux:select.option>
                    </flux:select>

                    @error('bank')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
            @endif

            {{-- Reference --}}
            <div>
                <flux:label>Reference</flux:label>
                <flux:input
                    wire:model="reference"
                    placeholder="Optional receipt or transaction reference"
                />

                @error('reference')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            {{-- Actions --}}
            <div class="flex flex-col-reverse gap-2 border-t border-gray-200 pt-4 sm:flex-row sm:justify-end dark:border-gray-700">
                <flux:button
                    type="button"
                    variant="ghost"
                    wire:click="closeForm"
                >
                    Cancel
                </flux:button>

                <flux:button
                    type="submit"
                    variant="primary"
                    wire:loading.attr="disabled"
                    wire:target="save"
                >
                    <span wire:loading.remove wire:target="save">
                        Save {{ $type }}
                    </span>

                    <span wire:loading wire:target="save">
                        Saving...
                    </span>
                </flux:button>
            </div>
        </form>
    </div>
</flux:modal>

    {{-- Transaction register --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="space-y-4 border-b border-gray-200 p-4 sm:p-5 dark:border-gray-700">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Recent transactions
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Search and review the money coming in and going out.
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search description, category or name..."
                    icon="magnifying-glass"
                />

                <flux:select wire:model.live="typeFilter">
                    <flux:select.option value="all">All transactions</flux:select.option>
                    <flux:select.option value="income">Income only</flux:select.option>
                    <flux:select.option value="expense">Expenses only</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="paymentMethodFilter">
                    <flux:select.option value="all">All payment methods</flux:select.option>
                    <flux:select.option value="bank">Bank Account</flux:select.option>
                    <flux:select.option value="mpesa">M-Pesa</flux:select.option>
                    <flux:select.option value="ecocash">EcoCash</flux:select.option>
                </flux:select>
            </div>
        </div>

        @if ($this->transactions->isEmpty())
            <div class="px-5 py-14 text-center">
                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-800">
                    <flux:icon name="receipt-percent" class="size-6" />
                </div>
                <h3 class="mt-4 font-medium text-gray-900 dark:text-white">
                    No transactions found
                </h3>
                <p class="mx-auto mt-1 max-w-md text-sm text-gray-500">
                    Try changing the dates or filters, or record your first income or expense.
                </p>
                <div class="mt-4 flex justify-center gap-2">
                    <flux:button variant="primary" wire:click="openForm('income')">
                        Add income
                    </flux:button>
                    <flux:button variant="filled" wire:click="openForm('expense')">
                        Add expense
                    </flux:button>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800/60">
                        <tr>
                            <th class="whitespace-nowrap px-5 py-3 font-medium">Date</th>
                            <th class="px-5 py-3 font-medium">Transaction</th>
                            <th class="px-5 py-3 font-medium">Category</th>
                            <th class="px-5 py-3 font-medium">Payment method</th>
                            <th class="px-5 py-3 text-right font-medium">Amount</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($this->transactions as $transaction)
                            <tr wire:key="transaction-{{ $transaction->id }}" class="transition hover:bg-gray-50/70 dark:hover:bg-gray-800/40">
                                <td class="whitespace-nowrap px-5 py-4 text-gray-500">
                                    {{ $transaction->date->format('d M Y') }}
                                </td>

                                <td class="min-w-52 px-5 py-4">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ $transaction->description }}
                                    </div>
                                    @if ($transaction->counterparty_name)
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ $transaction->counterparty_name }}
                                        </div>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-gray-600 dark:text-gray-300">
                                    {{ $transaction->category?->name ?? 'Uncategorised' }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4">
                                    <div class="text-gray-700 dark:text-gray-300">
                                        {{ [
                                            'bank' => 'Bank Account',
                                            'mpesa' => 'M-Pesa',
                                            'ecocash' => 'EcoCash',
                                        ][$transaction->payment_method] ?? $transaction->payment_method }}
                                    </div>
                                    @if ($transaction->payment_method === 'bank' && $transaction->bank)
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ [
                                                'standard_lesotho_bank' => 'Standard Lesotho Bank',
                                                'first_national_bank_lesotho' => 'First National Bank Lesotho',
                                                'nedbank_lesotho' => 'Nedbank Lesotho',
                                                'postbank_lesotho' => 'PostBank Lesotho',
                                                'other' => 'Other bank',
                                            ][$transaction->bank] ?? $transaction->bank }}
                                        </div>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <span class="font-semibold tabular-nums {{ $transaction->type === 'income' ? 'text-green-700 dark:text-green-400' : 'text-gray-900 dark:text-white' }}">
                                        {{ $transaction->type === 'income' ? '+' : '−' }}M {{ number_format((float) $transaction->amount, 2) }}
                                    </span>
                                    <div class="mt-1 text-xs text-gray-500">
                                        {{ ucfirst($transaction->type) }}
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                {{ $this->transactions->links() }}
            </div>
        @endif
    </div>
</div>
