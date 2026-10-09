<?php

use App\Models\Category;
use App\Models\OpeningBalance;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Attributes\Layout;

new 
#[Title('Financial Overview')] 
#[Layout('layouts::app.frontend')]
class extends Component
{
    public string $period = 'this_month';

    public function updatedPeriod(): void
    {
        // Livewire automatically re-renders dashboard data.
    }

    private function periodDates(): array
    {
        $now = now();

        return match ($this->period) {
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'this_year' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
            ],
            'last_30_days' => [
                $now->copy()->subDays(29)->startOfDay(),
                $now->copy()->endOfDay(),
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ],
        };
    }

    private function transactionsForUser()
    {
        return Transaction::query()
            ->where('user_id', auth()->id());
    }

    private function openingBalances(): Collection
    {
        return OpeningBalance::query()
            ->where('user_id', auth()->id())
            ->get();
    }

    private function totalsBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        $transactions = $this->transactionsForUser()
            ->whereBetween('date', [
                $from->toDateString(),
                $to->toDateString(),
            ]);

        return [
            'income' => (float) (clone $transactions)
                ->where('type', 'income')
                ->sum('amount'),

            'expenses' => (float) (clone $transactions)
                ->where('type', 'expense')
                ->sum('amount'),

            'count' => (clone $transactions)->count(),
        ];
    }

    private function accountBalances(): Collection
    {
        $accounts = collect();

        foreach ($this->openingBalances() as $opening) {
            $key = $opening->payment_method === 'bank'
                ? 'bank:' . ($opening->bank ?? 'other')
                : $opening->payment_method;

            if (! $accounts->has($key)) {
                $accounts->put($key, [
                    'key' => $key,
                    'payment_method' => $opening->payment_method,
                    'bank' => $opening->bank,
                    'opening' => 0.0,
                    'income' => 0.0,
                    'expenses' => 0.0,
                ]);
            }

            $account = $accounts->get($key);
            $account['opening'] += (float) $opening->amount;

            $accounts->put($key, $account);
        }

        $movementGroups = $this->transactionsForUser()
            ->selectRaw(
                'payment_method, bank, type, SUM(amount) as total'
            )
            ->groupBy('payment_method', 'bank', 'type')
            ->get();

        foreach ($movementGroups as $movement) {
            $key = $movement->payment_method === 'bank'
                ? 'bank:' . ($movement->bank ?? 'other')
                : $movement->payment_method;

            if (! $accounts->has($key)) {
                $accounts->put($key, [
                    'key' => $key,
                    'payment_method' => $movement->payment_method,
                    'bank' => $movement->bank,
                    'opening' => 0.0,
                    'income' => 0.0,
                    'expenses' => 0.0,
                ]);
            }

            $account = $accounts->get($key);

            if ($movement->type === 'income') {
                $account['income'] += (float) $movement->total;
            } elseif ($movement->type === 'expense') {
                $account['expenses'] += (float) $movement->total;
            }

            $accounts->put($key, $account);
        }

        return $accounts->map(function (array $account) {
            $account['balance'] = $account['opening']
                + $account['income']
                - $account['expenses'];

            $account['label'] = match ($account['payment_method']) {
                'bank' => $this->bankLabel($account['bank']),
                'mpesa' => 'M-Pesa',
                'ecocash' => 'EcoCash',
                default => ucfirst(
                    $account['payment_method'] ?? 'Other'
                ),
            };

            return $account;
        })->values();
    }

    private function bankLabel(?string $bank): string
    {
        return match ($bank) {
            'standard_lesotho_bank' => 'Standard Lesotho Bank',
            'first_national_bank_lesotho' => 'First National Bank Lesotho',
            'nedbank_lesotho' => 'Nedbank Lesotho',
            'postbank_lesotho' => 'PostBank Lesotho',
            'other' => 'Other Bank',
            default => 'Bank Account',
        };
    }

    private function monthlyTrend(): Collection
    {
        $start = now()->subMonths(5)->startOfMonth();
        $end = now()->endOfMonth();

        $transactions = $this->transactionsForUser()
            ->whereBetween('date', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->get(['date', 'type', 'amount'])
            ->groupBy(fn ($transaction) =>
                $transaction->date->format('Y-m')
            );

        return collect(range(0, 5))->map(function ($offset) use ($start, $transactions) {
            $month = $start->copy()->addMonths($offset);
            $items = $transactions->get($month->format('Y-m'), collect());

            $income = (float) $items
                ->where('type', 'income')->sum('amount');

            $expenses = (float) $items
                ->where('type', 'expense')->sum('amount');

            return [
                'label' => $month->format('M'),
                'income' => $income,
                'expenses' => $expenses,
                'net' => $income - $expenses,
            ];
        });
    }

    public function render()
    {
        [$from, $to] = $this->periodDates();

        $totals = $this->totalsBetween($from, $to);
        $accounts = $this->accountBalances();

        $allTransactions = $this->transactionsForUser();

        $recentTransactions = (clone $allTransactions)
            ->with('category')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $expenseCategories = (clone $allTransactions)
            ->with('category')
            ->where('type', 'expense')
            ->whereBetween('date', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->get()
            ->groupBy(fn ($transaction) =>
                $transaction->category?->name ?? 'Uncategorised'
            )
            ->map(fn ($items, $name) => [
                'name' => $name,
                'total' => (float) $items->sum('amount'),
                'count' => $items->count(),
            ])
            ->sortByDesc('total')
            ->take(5)
            ->values();

        $categoryTotal = $expenseCategories->sum('total');

        $visibleCategories = Category::query()
            ->where('user_id', auth()->id())
            ->where('is_hidden', false)
            ->count();

        $allTimeIncome = (float) $this->transactionsForUser()
            ->income()->sum('amount');

        $allTimeExpenses = (float) $this->transactionsForUser()
            ->expense()->sum('amount');

        $openingTotal = (float) $this->openingBalances()->sum('amount');

        return $this->view()->with([
            'totals' => $totals,
            'transactionCount' => (clone $allTransactions)->count(),
            'from' => $from,
            'to' => $to,
            'accounts' => $accounts,
            'totalBalance' => $accounts->sum('balance'),
            'openingTotal' => $openingTotal,
            'allTimeIncome' => $allTimeIncome,
            'allTimeExpenses' => $allTimeExpenses,
            'recentTransactions' => $recentTransactions,
            'expenseCategories' => $expenseCategories,
            'categoryTotal' => $categoryTotal,
            'visibleCategories' => $visibleCategories,
            'monthlyTrend' => $this->monthlyTrend(),
            
        ]);
    }
};
?>

<div id="top" class="space-y-8">

    {{-- Welcome header --}}
    <section class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div>

            <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900">
                Dashboard
            </h1>

            <p class="mt-2 text-sm text-gray-500">
                Here's what's happening with your business today.
            </p>
        </div>

        <a wire:navigate href="{{ route('transactions') }}"
           class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
            <i class="fa-solid fa-plus"></i>
            Add transaction
        </a>
    </section>

    {{-- Business activity --}}
    <section>
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900">
                    Your business at a glance
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    A quick summary of your recorded activity.
                </p>
            </div>

            <span class="hidden rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 sm:inline-flex">
                All time
            </span>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

            {{-- Total transactions --}}
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <i class="fa-solid fa-receipt"></i>
                    </span>

                    <span class="text-xs font-medium text-gray-400">
                        All records
                    </span>
                </div>

                <p class="mt-4 text-3xl font-bold tracking-tight text-gray-900">
                    {{ number_format($transactionCount) }}
                </p>

                <p class="mt-1 text-sm font-medium text-gray-700">
                    Transactions
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    Total transactions recorded
                </p>
            </div>

            {{-- Categories --}}
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50 text-purple-600">
                        <i class="fa-solid fa-tags"></i>
                    </span>

                    <span class="text-xs font-medium text-gray-400">
                        Available
                    </span>
                </div>

                <p class="mt-4 text-3xl font-bold tracking-tight text-gray-900">
                    {{ number_format($visibleCategories) }}
                </p>

                <p class="mt-1 text-sm font-medium text-gray-700">
                    Active categories
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    Categories available for transactions
                </p>
            </div>

            {{-- Payment accounts --}}
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-50 text-orange-600">
                        <i class="fa-solid fa-building-columns"></i>
                    </span>

                    <span class="text-xs font-medium text-gray-400">
                        Recorded
                    </span>
                </div>

                <p class="mt-4 text-3xl font-bold tracking-tight text-gray-900">
                    {{ number_format($accounts->count()) }}
                </p>

                <p class="mt-1 text-sm font-medium text-gray-700">
                    Payment accounts
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    Banks and mobile money accounts
                </p>
            </div>

            {{-- Current reporting period --}}
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 text-green-600">
                        <i class="fa-solid fa-calendar-check"></i>
                    </span>

                    <span class="text-xs font-medium text-gray-400">
                        Selected period
                    </span>
                </div>

                <p class="mt-4 text-3xl font-bold tracking-tight text-gray-900">
                    {{ number_format($totals['count']) }}
                </p>

                <p class="mt-1 text-sm font-medium text-gray-700">
                    Period transactions
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    {{ $from->format('d M') }} – {{ $to->format('d M Y') }}
                </p>
            </div>

        </div>
    </section>

    {{-- Quick actions --}}
    <section>
        <div class="mb-4">
            <h2 class="text-base font-semibold text-gray-900">
                Quick actions
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Get things done without navigating through menus.
            </p>
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">

            <a href="{{ route('transactions') }}"
               class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 transition group-hover:bg-blue-100">
                    <i class="fa-solid fa-plus text-lg"></i>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-gray-900">
                        New transaction
                    </span>
                    <span class="mt-1 block text-xs text-gray-500">
                        Record money in or out
                    </span>
                </span>
            </a>

            <a href="{{ route('transactions') }}"
               class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-green-300 hover:shadow-sm">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-green-50 text-green-600 transition group-hover:bg-green-100">
                    <i class="fa-solid fa-list-check text-lg"></i>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-gray-900">
                        Transactions
                    </span>
                    <span class="mt-1 block text-xs text-gray-500">
                        View and manage records
                    </span>
                </span>
            </a>

            <a href="#recent-activity"
               class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-purple-300 hover:shadow-sm">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-purple-50 text-purple-600 transition group-hover:bg-purple-100">
                    <i class="fa-solid fa-clock-rotate-left text-lg"></i>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-gray-900">
                        Recent activity
                    </span>
                    <span class="mt-1 block text-xs text-gray-500">
                        See the latest changes
                    </span>
                </span>
            </a>

            <a href="#payment-accounts"
               class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-orange-300 hover:shadow-sm">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600 transition group-hover:bg-orange-100">
                    <i class="fa-solid fa-wallet text-lg"></i>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-gray-900">
                        Payment accounts
                    </span>
                    <span class="mt-1 block text-xs text-gray-500">
                        Check recorded balances
                    </span>
                </span>
            </a>

        </div>
    </section>

    {{-- Financial snapshot --}}
    <section class="rounded-2xl bg-gray-900 p-5 text-white shadow-sm sm:p-7">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-medium text-gray-400">
                    FINANCIAL SNAPSHOT
                </p>

                <h2 class="mt-1 text-xl font-semibold">
                    Your money at a glance
                </h2>

                <p class="mt-1 text-sm text-gray-400">
                    Choose a period to update your financial summary.
                </p>
            </div>

            <select wire:model.live="period"
                    class="rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-white focus:border-blue-500 focus:outline-none">
                <option value="this_month">This month</option>
                <option value="last_month">Last month</option>
                <option value="last_30_days">Last 30 days</option>
                <option value="this_year">This year</option>
            </select>
        </div>

        <p class="mt-4 text-xs text-gray-400">
            {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}
        </p>

        <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-3">

            <div>
                <div class="flex items-center gap-2 text-sm text-gray-400">
                    <i class="fa-solid fa-arrow-down-long text-green-400"></i>
                    Income
                </div>

                <p class="mt-2 text-2xl font-bold text-green-400">
                    M{{ number_format($totals['income'], 2) }}
                </p>
            </div>

            <div>
                <div class="flex items-center gap-2 text-sm text-gray-400">
                    <i class="fa-solid fa-arrow-up-long text-red-400"></i>
                    Expenses
                </div>

                <p class="mt-2 text-2xl font-bold text-red-400">
                    M{{ number_format($totals['expenses'], 2) }}
                </p>
            </div>

            <div>
                @php
                    $netMovement = $totals['income'] - $totals['expenses'];
                @endphp

                <div class="flex items-center gap-2 text-sm text-gray-400">
                    <i class="fa-solid fa-scale-balanced"></i>
                    Net movement
                </div>

                <p class="mt-2 text-2xl font-bold {{ $netMovement >= 0 ? 'text-green-400' : 'text-red-400' }}">
                    {{ $netMovement < 0 ? '-' : '' }}M{{ number_format(abs($netMovement), 2) }}
                </p>
            </div>

        </div>
    </section>

    {{-- Recent activity and account overview --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        {{-- Recent transactions --}}
        <section id="recent-activity"
                 class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm xl:col-span-2">

            <div class="flex items-center justify-between gap-3 border-b border-gray-100 p-5">
                <div>
                    <h2 class="font-semibold text-gray-900">
                        Recent transactions
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Your latest recorded business activity.
                    </p>
                </div>

                <a href="{{ route('transactions') }}"
                   class="whitespace-nowrap text-sm font-semibold text-blue-600 hover:text-blue-800">
                    View all
                    <i class="fa-solid fa-arrow-right ml-1 text-xs"></i>
                </a>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse ($recentTransactions as $transaction)
                    <div wire:key="recent-transaction-{{ $transaction->id }}"
                         class="flex items-center gap-3 px-5 py-4 transition hover:bg-gray-50">

                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $transaction->isIncome() ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' }}">
                            <i class="fa-solid {{ $transaction->isIncome() ? 'fa-arrow-down' : 'fa-arrow-up' }}"></i>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-gray-900">
                                {{ $transaction->description ?: 'No description' }}
                            </p>

                            <p class="mt-1 truncate text-xs text-gray-500">
                                {{ $transaction->category?->name ?? 'Uncategorised' }}
                                <span class="mx-1">·</span>
                                {{ $transaction->date?->format('d M Y') }}
                            </p>
                        </div>

                        <div class="shrink-0 text-right">
                            <p class="text-sm font-semibold {{ $transaction->isIncome() ? 'text-green-600' : 'text-gray-900' }}">
                                {{ $transaction->isIncome() ? '+' : '-' }}{{ $transaction->displayAmount() }}
                            </p>

                            <p class="mt-1 text-xs text-gray-400">
                                {{ $transaction->paymentMethodLabel() }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-12 text-center">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                            <i class="fa-solid fa-receipt text-lg"></i>
                        </span>

                        <h3 class="mt-3 text-sm font-semibold text-gray-900">
                            No transactions yet
                        </h3>

                        <p class="mt-1 text-sm text-gray-500">
                            Start recording your business activity.
                        </p>

                        <a href="{{ route('transactions') }}"
                           class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-800">
                            <i class="fa-solid fa-plus"></i>
                            Add your first transaction
                        </a>
                    </div>
                @endforelse
            </div>

            @if ($recentTransactions->isNotEmpty())
                <div class="border-t border-gray-100 bg-gray-50 px-5 py-3">
                    <p class="text-xs text-gray-500">
                        Showing your {{ $recentTransactions->count() }} most recent
                        {{ $recentTransactions->count() === 1 ? 'transaction' : 'transactions' }}.
                    </p>
                </div>
            @endif
        </section>

        {{-- Payment account overview --}}
        <section id="payment-accounts"
                 class="rounded-xl border border-gray-200 bg-white shadow-sm">

            <div class="border-b border-gray-100 p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900">
                        Payment accounts
                    </h2>

                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <i class="fa-solid fa-wallet"></i>
                    </span>
                </div>

                <p class="mt-1 text-sm text-gray-500">
                    Balances across your recorded accounts.
                </p>
            </div>

            <div class="p-5">
                @forelse ($accounts as $account)
                    <div wire:key="dashboard-account-{{ $account['key'] }}"
                         class="flex items-center gap-3 {{ ! $loop->first ? 'mt-5 border-t border-gray-100 pt-5' : '' }}">

                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                            <i class="fa-solid {{ $account['payment_method'] === 'bank' ? 'fa-building-columns' : 'fa-wallet' }}"></i>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900">
                                {{ $account['label'] }}
                            </p>

                            <p class="mt-1 text-xs text-gray-500">
                                {{ ucfirst($account['payment_method'] ?? 'Other') }}
                            </p>
                        </div>

                        <p class="shrink-0 text-sm font-semibold {{ $account['balance'] < 0 ? 'text-red-600' : 'text-gray-900' }}">
                            M{{ number_format($account['balance'], 2) }}
                        </p>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <i class="fa-solid fa-wallet text-2xl text-gray-300"></i>

                        <p class="mt-3 text-sm font-medium text-gray-700">
                            No accounts recorded
                        </p>

                        <p class="mt-1 text-xs text-gray-500">
                            Accounts will appear here as you record opening balances or transactions.
                        </p>
                    </div>
                @endforelse

                @if ($accounts->isNotEmpty())
                    <div class="mt-6 border-t border-gray-100 pt-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-600">
                                Combined balance
                            </span>

                            <span class="text-base font-bold {{ $totalBalance < 0 ? 'text-red-600' : 'text-gray-900' }}">
                                M{{ number_format($totalBalance, 2) }}
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        </section>

    </div>

    {{-- Expense category snapshot --}}
    <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-gray-100 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">
                    Where your money goes
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    Your biggest expense categories for the selected period.
                </p>
            </div>

            <span class="text-xs font-medium text-gray-500">
                {{ $from->format('d M') }} – {{ $to->format('d M Y') }}
            </span>
        </div>

        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-5">
            @forelse ($expenseCategories as $category)
                @php
                    $percentage = $categoryTotal > 0
                        ? ($category['total'] / $categoryTotal) * 100
                        : 0;
                @endphp

                <div class="rounded-xl bg-gray-50 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-gray-500 shadow-sm">
                            <i class="fa-solid fa-tag"></i>
                        </span>

                        <span class="text-xs text-gray-400">
                            {{ number_format($percentage, 0) }}%
                        </span>
                    </div>

                    <p class="mt-4 truncate text-sm font-medium text-gray-700"
                       title="{{ $category['name'] }}">
                        {{ $category['name'] }}
                    </p>

                    <p class="mt-1 text-lg font-bold text-gray-900">
                        M{{ number_format($category['total'], 2) }}
                    </p>

                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-200">
                        <div class="h-full rounded-full bg-blue-500"
                             style="width: {{ min(100, $percentage) }}%">
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-6 text-center sm:col-span-2 lg:col-span-5">
                    <i class="fa-solid fa-chart-simple text-2xl text-gray-300"></i>

                    <p class="mt-2 text-sm font-medium text-gray-700">
                        No expense data yet
                    </p>

                    <p class="mt-1 text-xs text-gray-500">
                        Your expense breakdown will appear here when you record expenses.
                    </p>
                </div>
            @endforelse
        </div>
    </section>

    {{-- Reporting period details --}}
    <section class="rounded-xl border border-gray-200 bg-white p-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                    <i class="fa-solid fa-calendar-days"></i>
                </span>

                <div>
                    <h2 class="text-sm font-semibold text-gray-900">
                        Reporting period
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}
                    </p>

                    <p class="mt-1 text-xs text-gray-400">
                        {{ number_format($totals['count']) }}
                        {{ $totals['count'] === 1 ? 'transaction' : 'transactions' }}
                        recorded during this period.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('transactions') }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    <i class="fa-solid fa-list"></i>
                    Manage transactions
                </a>

                <a href="#top"
                   class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    <i class="fa-solid fa-arrow-up"></i>
                    Back to top
                </a>
            </div>
        </div>
    </section>

</div>