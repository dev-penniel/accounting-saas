
<?php

use App\Models\OpeningBalance;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::app.frontend')]
class extends Component
{
    public bool $showForm = false;

    public ?int $editingBalanceId = null;

    public string $payment_method = 'bank';
    public string $bank = '';
    public string $amount = '';

    public function updatedPaymentMethod(): void
    {
        if ($this->payment_method !== 'bank') {
            $this->bank = '';
        }

        $this->resetValidation();
    }

    #[Computed]
    public function balances()
    {
        return OpeningBalance::query()
            ->where('user_id', auth()->id())
            ->orderByRaw("CASE payment_method WHEN 'bank' THEN 1 WHEN 'mpesa' THEN 2 WHEN 'ecocash' THEN 3 ELSE 4 END")
            ->orderBy('bank')
            ->get();
    }

    #[Computed]
    public function summary(): array
    {
        $balances = $this->balances;

        return [
            'total' => $balances->sum(fn ($balance) => (float) $balance->amount),
            'bank' => $balances
                ->where('payment_method', 'bank')
                ->sum(fn ($balance) => (float) $balance->amount),
            'mobile' => $balances
                ->whereIn('payment_method', ['mpesa', 'ecocash'])
                ->sum(fn ($balance) => (float) $balance->amount),
            'accounts' => $balances->count(),
        ];
    }

    public function openCreate(): void
    {
        $this->resetValidation();

        $this->editingBalanceId = null;
        $this->payment_method = 'bank';
        $this->bank = '';
        $this->amount = '';

        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $balance = OpeningBalance::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $this->resetValidation();

        $this->editingBalanceId = $balance->id;
        $this->payment_method = $balance->payment_method;
        $this->bank = $balance->bank ?? '';
        $this->amount = (string) $balance->amount;

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate([
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
            'amount' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
        ]);

        $bank = $validated['payment_method'] === 'bank'
            ? $validated['bank']
            : null;

        $duplicateQuery = OpeningBalance::query()
            ->where('user_id', auth()->id())
            ->where('payment_method', $validated['payment_method']);

        if ($bank === null) {
            $duplicateQuery->whereNull('bank');
        } else {
            $duplicateQuery->where('bank', $bank);
        }

        if ($this->editingBalanceId !== null) {
            $duplicateQuery->where('id', '!=', $this->editingBalanceId);
        }

        if ($duplicateQuery->exists()) {
            $this->addError(
                'payment_method',
                'An opening balance already exists for this account. Edit the existing entry instead.'
            );

            return;
        }

        $data = [
            'payment_method' => $validated['payment_method'],
            'bank' => $bank,
            'amount' => $validated['amount'],
        ];

        if ($this->editingBalanceId !== null) {
            $balance = OpeningBalance::query()
                ->where('user_id', auth()->id())
                ->findOrFail($this->editingBalanceId);

            $balance->update($data);

            $message = 'Opening balance updated successfully.';
        } else {
            OpeningBalance::create([
                'user_id' => auth()->id(),
                ...$data,
            ]);

            $message = 'Opening balance saved successfully.';
        }

        $this->showForm = false;

        $this->reset([
            'editingBalanceId',
            'bank',
            'amount',
        ]);

        $this->payment_method = 'bank';

        unset($this->balances, $this->summary);

        session()->flash('success', $message);
    }
};
?>

<div class="mx-auto max-w-6xl space-y-6">

    {{-- Header --}}
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <flux:heading size="xl">Opening balances</flux:heading>

            <flux:subheading>
                Record the money your business had when you started bookkeeping.
            </flux:subheading>
        </div>

        <flux:button
            wire:click="openCreate"
            variant="primary"
            icon="plus"
        >
            Add opening balance
        </flux:button>
    </div>

    {{-- Success message --}}
    @if (session()->has('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    {{-- Explanation --}}
    @if ($this->balances->isEmpty())
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                    <flux:icon name="wallet" class="size-5" />
                </div>

                <div class="space-y-1">
                    <flux:heading size="lg">
                        Start with your current balances
                    </flux:heading>

                    <flux:text color="green" class="leading-relaxed">
                        Enter how much money you had in each bank account and mobile
                        money wallet at the date you began recording transactions.
                        These amounts establish your starting position; they are
                        not new income.
                    </flux:text>
                </div>
            </div>
        </div>
    @endif

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center gap-2">
                <flux:icon name="wallet" class="size-5 text-zinc-500" />
                <flux:text color="green" size="sm">Total opening balance</flux:text>
            </div>

            <flux:heading size="xl" class="mt-3">
                M{{ number_format($this->summary['total'], 2) }}
            </flux:heading>

            <flux:text color="green" size="sm" class="mt-1">
                Across all recorded accounts
            </flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center gap-2">
                <flux:icon name="home" class="size-5 text-zinc-500" />
                <flux:text color="green" size="sm">Bank accounts</flux:text>
            </div>

            <flux:heading size="xl" class="mt-3">
                M{{ number_format($this->summary['bank'], 2) }}
            </flux:heading>

            <flux:text color="green" size="sm" class="mt-1">
                Starting bank balances
            </flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center gap-2">
                <flux:icon name="home" class="size-5 text-zinc-500" />
                <flux:text color="green" size="sm">Mobile money</flux:text>
            </div>

            <flux:heading size="xl" class="mt-3">
                M{{ number_format($this->summary['mobile'], 2) }}
            </flux:heading>

            <flux:text color="green" size="sm" class="mt-1">
                M-Pesa and EcoCash combined
            </flux:text>
        </div>
    </div>

    {{-- Account list --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
            <div>
                <flux:heading size="lg">Your accounts</flux:heading>

                <flux:text color="green" size="sm">
                    {{ $this->summary['accounts'] }}
                    {{ \Illuminate\Support\Str::plural('account', $this->summary['accounts']) }}
                    recorded
                </flux:text>
            </div>
        </div>

        @forelse ($this->balances as $balance)
            <div
                wire:key="opening-balance-{{ $balance->id }}"
                class="flex flex-col gap-3 border-b border-zinc-100 px-5 py-4 last:border-b-0 dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                        <flux:icon
                            name="{{ $balance->payment_method === 'bank' ? 'home' : 'home' }}"
                            class="size-5"
                        />
                    </div>

                    <div class="min-w-0">
                        <flux:text class="font-medium">
                            {{ $balance->paymentMethodLabel() }}
                        </flux:text>

                        <flux:text color="green" size="sm">
                            {{ $balance->isBank() ? ($balance->bankLabel() ?? 'Bank account') : $balance->paymentMethodLabel() }}
                        </flux:text>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-4 sm:justify-end">
                    <div class="sm:text-right">
                        <flux:heading size="lg">
                            {{ $balance->formattedAmount() }}
                        </flux:heading>

                        <flux:text color="green" size="xs">
                            Opening balance
                        </flux:text>
                    </div>

                    <flux:button
                        wire:click="edit({{ $balance->id }})"
                        variant="ghost"
                        size="sm"
                        icon="pencil"
                    >
                        Edit
                    </flux:button>
                </div>
            </div>
        @empty
            <div class="px-5 py-12 text-center">
                <flux:icon name="wallet" class="mx-auto mb-3 size-9 text-zinc-400" />

                <flux:heading>No opening balances yet</flux:heading>

                <flux:text color="green" class="mx-auto mt-1 max-w-md">
                    Add the money you started with in your bank accounts and
                    mobile wallets. You can edit these amounts later.
                </flux:text>

                <div class="mt-4">
                    <flux:button
                        wire:click="openCreate"
                        variant="primary"
                        icon="plus"
                    >
                        Add opening balance
                    </flux:button>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Create / edit modal --}}
    <flux:modal
        wire:model="showForm"
        class="w-full max-w-lg"
    >
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingBalanceId ? 'Edit opening balance' : 'Add opening balance' }}
                </flux:heading>

                <flux:subheading>
                    Record the starting amount for one account or wallet.
                </flux:subheading>
            </div>

            <form wire:submit="save" class="space-y-5">

                <div>
                    <flux:select
                        wire:model.live="payment_method"
                        label="Account type"
                        required
                    >
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
                        <flux:text color="danger" size="sm" class="mt-1">
                            {{ $message }}
                        </flux:text>
                    @enderror
                </div>

                @if ($payment_method === 'bank')
                    <div>
                        <flux:select
                            wire:model="bank"
                            label="Bank"
                            required
                        >
                            <flux:select.option value="">
                                Select a bank
                            </flux:select.option>

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
                            <flux:text color="danger" size="sm" class="mt-1">
                                {{ $message }}
                            </flux:text>
                        @enderror
                    </div>
                @endif

                <div>
                    <flux:input
                        wire:model="amount"
                        label="Opening amount (M)"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0.00"
                        required
                    />

                    <flux:text color="green" size="sm" class="mt-1">
                        Enter the actual amount available when you began bookkeeping.
                    </flux:text>

                    @error('amount')
                        <flux:text color="danger" size="sm" class="mt-1">
                            {{ $message }}
                        </flux:text>
                    @enderror
                </div>

                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                    <flux:text size="sm" color="green">
                        Opening balances are starting funds, not new income.
                        Enter each account or wallet only once.
                    </flux:text>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button
                        type="button"
                        wire:click="closeForm"
                        variant="ghost"
                    >
                        Cancel
                    </flux:button>

                    <flux:button
                        type="submit"
                        variant="primary"
                    >
                        {{ $editingBalanceId ? 'Save changes' : 'Save opening balance' }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

</div>
