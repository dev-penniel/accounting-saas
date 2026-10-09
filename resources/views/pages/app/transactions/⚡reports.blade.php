<?php

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    public string $report = 'summary';

    public string $fromDate = '';
    public string $toDate = '';

    public string $compareFrom = '';
    public string $compareTo = '';
    public string $compareFromTwo = '';
    public string $compareToTwo = '';

    public string $search = '';
    public string $typeFilter = 'all';
    public string $paymentMethodFilter = 'all';
    public string $bankFilter = 'all';

    public function mount(): void
    {
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();

        $this->compareFrom = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $this->compareTo = now()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $this->compareFromTwo = now()->startOfMonth()->toDateString();
        $this->compareToTwo = now()->toDateString();
    }

    private function user()
    {
        abort_unless(auth()->check(), 403);

        return auth()->user();
    }

    private function validRange(string $from, string $to): bool
    {
        return $from !== ''
            && $to !== ''
            && strtotime($from) !== false
            && strtotime($to) !== false
            && $from <= $to;
    }

    
    private function periodQuery(string $from, string $to): Builder
    {
        $query = $this->user()->transactions()->getQuery();

        if ($this->validRange($from, $to)) {
            $query->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function applyPaymentFilters(Builder $query): Builder
    {
        if ($this->paymentMethodFilter !== 'all') {
            $query->where('payment_method', $this->paymentMethodFilter);
        }

        if (
            $this->paymentMethodFilter === 'bank'
            && $this->bankFilter !== 'all'
        ) {
            $query->where('bank', $this->bankFilter);
        }

        return $query;
    }

    private function applySearch(Builder $query): Builder
    {
        $search = trim($this->search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $like = '%' . $search . '%';

            $q->where('description', 'like', $like)
                ->orWhere('counterparty_name', 'like', $like)
                ->orWhere('reference', 'like', $like)
                ->orWhereHas('category', fn ($category) =>
                    $category->where('name', 'like', $like)
                );
        });
    }

    private function totals(string $from, string $to): array
    {
        $row = $this->periodQuery($from, $to)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expenses,
                COUNT(*) AS transaction_count
            ")
            ->first();

        $income = (float) $row->income;
        $expenses = (float) $row->expenses;

        return [
            'income' => $income,
            'expenses' => $expenses,
            'net' => $income - $expenses,
            'count' => (int) $row->transaction_count,
        ];
    }

    private function openingBalance(string $method, ?string $bank = null): float
    {
        $query = $this->user()->openingBalances()
            ->where('payment_method', $method);

        if ($method === 'bank') {
            if ($bank !== null) {
                $query->where('bank', $bank);
            } else {
                $query->whereNotNull('bank');
            }
        }

        return (float) $query->sum('amount');
    }

    public function updatedReport(): void
    {
        $this->resetPage();
        $this->resetFilters();
    }

    public function updatedFromDate(): void
    {
        $this->resetPage();
    }

    public function updatedToDate(): void
    {
        $this->resetPage();
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

        if ($this->paymentMethodFilter !== 'bank') {
            $this->bankFilter = 'all';
        }
    }

    public function updatedBankFilter(): void
    {
        $this->resetPage();
    }

    private function resetFilters(): void
    {
        $this->search = '';
        $this->typeFilter = 'all';
        $this->paymentMethodFilter = 'all';
        $this->bankFilter = 'all';
    }

    public function setRange(string $range): void
    {
        $today = now();

        match ($range) {
            'this_month' => [
                $this->fromDate = $today->copy()->startOfMonth()->toDateString(),
                $this->toDate = $today->toDateString(),
            ],
            'last_month' => [
                $this->fromDate = $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $this->toDate = $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'this_year' => [
                $this->fromDate = $today->copy()->startOfYear()->toDateString(),
                $this->toDate = $today->toDateString(),
            ],
            'all_time' => [
                $this->fromDate = $this->user()->transactions()->min('date')
                    ? Carbon::parse($this->user()->transactions()->min('date'))->toDateString()
                    : $today->toDateString(),
                $this->toDate = $today->toDateString(),
            ],
            default => null,
        };

        $this->resetPage();
    }

    #[Computed]
    public function summary(): array
    {
        return $this->totals($this->fromDate, $this->toDate);
    }

    #[Computed]
    public function comparison(): array
    {
        $first = $this->totals($this->compareFrom, $this->compareTo);
        $second = $this->totals($this->compareFromTwo, $this->compareToTwo);

        $result = [];

        foreach (['income', 'expenses', 'net'] as $key) {
            $old = $first[$key];
            $new = $second[$key];
            $change = $new - $old;

            $result[$key] = [
                'first' => $old,
                'second' => $new,
                'change' => $change,
                'percentage' => $old == 0 ? null : ($change / abs($old)) * 100,
            ];
        }

        return $result;
    }

    #[Computed]
    public function categoryBreakdown(): Collection
    {
        $query = $this->periodQuery($this->fromDate, $this->toDate)
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->select(
                'transactions.type',
                'categories.id as category_id',
                'categories.name as category_name'
            )
            ->selectRaw('SUM(transactions.amount) as total')
            ->groupBy(
                'transactions.type',
                'categories.id',
                'categories.name'
            )
            ->orderBy('transactions.type')
            ->orderByDesc('total');

        return $query->get()->groupBy('type');
    }

    #[Computed]
    public function paymentBreakdown(): array
    {
        $rows = $this->periodQuery($this->fromDate, $this->toDate)
            ->select('payment_method', 'bank', 'type')
            ->selectRaw('SUM(amount) as total')
            ->groupBy('payment_method', 'bank', 'type')
            ->get();

        $methods = [
            'bank' => ['label' => 'Bank Account', 'income' => 0.0, 'expenses' => 0.0],
            'mpesa' => ['label' => 'M-Pesa', 'income' => 0.0, 'expenses' => 0.0],
            'ecocash' => ['label' => 'EcoCash', 'income' => 0.0, 'expenses' => 0.0],
        ];

        $banks = [
            'standard_lesotho_bank' => ['label' => 'Standard Lesotho Bank', 'income' => 0.0, 'expenses' => 0.0],
            'first_national_bank_lesotho' => ['label' => 'First National Bank Lesotho', 'income' => 0.0, 'expenses' => 0.0],
            'nedbank_lesotho' => ['label' => 'Nedbank Lesotho', 'income' => 0.0, 'expenses' => 0.0],
            'postbank_lesotho' => ['label' => 'PostBank Lesotho', 'income' => 0.0, 'expenses' => 0.0],
            'other' => ['label' => 'Other bank', 'income' => 0.0, 'expenses' => 0.0],
        ];

        foreach ($rows as $row) {
            $key = $row->type === 'income' ? 'income' : 'expenses';
            $method = $row->payment_method;

            if (isset($methods[$method])) {
                $methods[$method][$key] += (float) $row->total;
            }

            if ($method === 'bank' && isset($banks[$row->bank])) {
                $banks[$row->bank][$key] += (float) $row->total;
            }
        }

        foreach ($methods as &$method) {
            $method['net'] = $method['income'] - $method['expenses'];
        }

        foreach ($banks as &$bank) {
            $bank['net'] = $bank['income'] - $bank['expenses'];
        }

        unset($method, $bank);

        return ['methods' => $methods, 'banks' => $banks];
    }

    private function registerQuery(): Builder
    {
        $query = $this->periodQuery($this->fromDate, $this->toDate)
            ->with('category');

        if ($this->report === 'income_register') {
            $query->where('type', 'income');
        } elseif ($this->report === 'expense_register') {
            $query->where('type', 'expense');
        } elseif ($this->report === 'payment_register') {
            $this->applyPaymentFilters($query);
        }

        if ($this->typeFilter !== 'all') {
            $query->where('type', $this->typeFilter);
        }

        $this->applyPaymentFilters($query);
        $this->applySearch($query);

        return $query;
    }

    #[Computed]
    public function registerTotals(): array
    {
        $query = clone $this->registerQuery();

        $row = $query->selectRaw("
            COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expenses,
            COUNT(*) AS transaction_count
        ")->first();

        return [
            'income' => (float) $row->income,
            'expenses' => (float) $row->expenses,
            'net' => (float) $row->income - (float) $row->expenses,
            'count' => (int) $row->transaction_count,
        ];
    }

    #[Computed]
    public function registerRows(): LengthAwarePaginator
    {
        $query = $this->registerQuery()
            ->orderBy('date')
            ->orderBy('id');

        $rows = $query->get();

        $running = 0.0;

        if ($this->report === 'payment_register') {
            $method = $this->paymentMethodFilter;

            if ($method !== 'all') {
                $running = $method === 'bank'
                    ? $this->openingBalance(
                        'bank',
                        $this->bankFilter === 'all' ? null : $this->bankFilter
                    )
                    : $this->openingBalance($method);
            }
        }

        $rows = $rows->map(function ($transaction) use (&$running) {
            if ($this->report === 'payment_register') {
                $running += $transaction->type === 'income'
                    ? (float) $transaction->amount
                    : -(float) $transaction->amount;
            } else {
                $running += (float) $transaction->amount;
            }

            $transaction->running_total = $running;

            return $transaction;
        });

        // Running totals are calculated chronologically; display newest first.
        $rows = $rows->reverse()->values();

        $perPage = 15;
        $page = $this->getPage();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }

    #[Computed]
    public function paymentRegisterOpeningBalance(): ?float
    {
        if ($this->report !== 'payment_register' || $this->paymentMethodFilter === 'all') {
            return null;
        }

        return $this->paymentMethodFilter === 'bank'
            ? $this->openingBalance(
                'bank',
                $this->bankFilter === 'all' ? null : $this->bankFilter
            )
            : $this->openingBalance($this->paymentMethodFilter);
    }

    public function updatedCompareFrom(): void
    {
        $this->resetPage();
    }

    public function updatedCompareTo(): void
    {
        $this->resetPage();
    }

    public function updatedCompareFromTwo(): void
    {
        $this->resetPage();
    }

    public function updatedCompareToTwo(): void
    {
        $this->resetPage();
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6 pb-10">

    {{-- Heading --}}
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
            Financial reports
        </h1>
        <p class="mt-1 text-sm text-gray-500">
            Understand your income, expenses and where your business money goes.
        </p>
    </div>

    {{-- Report selector --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @php
                $reportOptions = [
                    'summary' => ['Overview', 'Income, expenses and net result', 'home'],
                    'income_register' => ['Income register', 'All income entries', 'arrow-trending-up'],
                    'expense_register' => ['Expense register', 'All expense entries', 'arrow-trending-down'],
                    'category_breakdown' => ['Category breakdown', 'Where money comes from and goes', 'chart-pie'],
                    'comparison' => ['Period comparison', 'Compare two periods', 'home'],
                    'payment_breakdown' => ['Payment breakdown', 'Compare payment methods and banks', 'wallet'],
                    'payment_register' => ['Payment register', 'Reconcile an account or wallet', 'home'],
                ];
            @endphp

            @foreach ($reportOptions as $key => [$label, $description, $icon])
                <button
                    type="button"
                    wire:click="$set('report', '{{ $key }}')"
                    @class([
                        'rounded-lg border p-3 text-left transition',
                        'border-gray-900 bg-gray-50 dark:border-gray-200 dark:bg-gray-800' => $report === $key,
                        'border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800' => $report !== $key,
                    ])
                >
                    <div class="flex items-center gap-2">
                        <flux:icon :name="$icon" class="size-5 text-gray-500" />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $label }}</span>
                    </div>
                    <p class="mt-2 text-xs leading-5 text-gray-500">{{ $description }}</p>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Date filters --}}
    @if ($report !== 'comparison')
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                <div class="grid flex-1 gap-3 sm:grid-cols-2">
                    <div>
                        <flux:label>From date</flux:label>
                        <flux:input type="date" wire:model.live="fromDate" />
                    </div>
                    <div>
                        <flux:label>To date</flux:label>
                        <flux:input type="date" wire:model.live="toDate" />
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" variant="ghost" wire:click="setRange('this_month')">This month</flux:button>
                    <flux:button size="sm" variant="ghost" wire:click="setRange('last_month')">Last month</flux:button>
                    <flux:button size="sm" variant="ghost" wire:click="setRange('this_year')">This year</flux:button>
                    <flux:button size="sm" variant="ghost" wire:click="setRange('all_time')">All time</flux:button>
                </div>
            </div>

            @if ($fromDate && $toDate && $fromDate > $toDate)
                <p class="mt-3 text-sm text-red-600">The start date must be before or equal to the end date.</p>
            @endif
        </div>
    @endif

    {{-- SUMMARY --}}
    @if ($report === 'summary')
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Income', $this->summary['income'], 'green'],
                ['Expenses', $this->summary['expenses'], 'red'],
                ['Net result', $this->summary['net'], 'neutral'],
                ['Transactions', $this->summary['count'], 'neutral'],
            ] as [$label, $value, $tone])
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-sm text-gray-500">{{ $label }}</p>
                    <p @class([
                        'mt-3 text-2xl font-semibold tabular-nums',
                        'text-green-700 dark:text-green-400' => $tone === 'green',
                        'text-red-700 dark:text-red-400' => $tone === 'red',
                        'text-gray-900 dark:text-white' => $tone === 'neutral',
                    ])>
                        @if ($label === 'Transactions')
                            {{ number_format($value) }}
                        @else
                            M {{ number_format((float) $value, 2) }}
                        @endif
                    </p>
                    @if ($label === 'Net result')
                        <p class="mt-1 text-xs text-gray-500">Income minus expenses</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Income vs expenses</h2>
            <p class="mt-1 text-sm text-gray-500">Compare money received and money spent during this period.</p>

            @php
                $maxSummary = max($this->summary['income'], $this->summary['expenses'], 1);
            @endphp

            <div class="mt-6 space-y-5">
                @foreach ([
                    ['Income', $this->summary['income'], 'bg-green-600'],
                    ['Expenses', $this->summary['expenses'], 'bg-red-500'],
                ] as [$label, $amount, $barColor])
                    <div>
                        <div class="mb-2 flex justify-between gap-4 text-sm">
                            <span class="text-gray-600 dark:text-gray-300">{{ $label }}</span>
                            <span class="font-medium tabular-nums text-gray-900 dark:text-white">M {{ number_format($amount, 2) }}</span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="{{ $barColor }} h-full rounded-full" style="width: {{ min(100, ($amount / $maxSummary) * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- PERIOD COMPARISON --}}
    @if ($report === 'comparison')
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="font-semibold text-gray-900 dark:text-white">Choose two periods</h2>
            <p class="mt-1 text-sm text-gray-500">Select the date ranges you want to compare.</p>

            <div class="mt-4 grid gap-5 lg:grid-cols-2">
                <div class="space-y-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                    <p class="text-sm font-medium">Period A</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><flux:label>From</flux:label><flux:input type="date" wire:model.live="compareFrom" /></div>
                        <div><flux:label>To</flux:label><flux:input type="date" wire:model.live="compareTo" /></div>
                    </div>
                </div>

                <div class="space-y-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                    <p class="text-sm font-medium">Period B</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><flux:label>From</flux:label><flux:input type="date" wire:model.live="compareFromTwo" /></div>
                        <div><flux:label>To</flux:label><flux:input type="date" wire:model.live="compareToTwo" /></div>
                    </div>
                </div>
            </div>

            @if (
                !$this->validRange($compareFrom, $compareTo)
                || !$this->validRange($compareFromTwo, $compareToTwo)
            )
                <p class="mt-3 text-sm text-red-600">Enter valid date ranges for both periods.</p>
            @else
                <div class="mt-5 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="py-3 pr-4">Measure</th>
                                <th class="py-3 pr-4">Period A</th>
                                <th class="py-3 pr-4">Period B</th>
                                <th class="py-3 pr-4">Change</th>
                                <th class="py-3">Change %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach (['income' => 'Income', 'expenses' => 'Expenses', 'net' => 'Net result'] as $key => $label)
                                <tr>
                                    <td class="py-4 pr-4 font-medium">{{ $label }}</td>
                                    <td class="py-4 pr-4 tabular-nums">M {{ number_format($this->comparison[$key]['first'], 2) }}</td>
                                    <td class="py-4 pr-4 tabular-nums">M {{ number_format($this->comparison[$key]['second'], 2) }}</td>
                                    <td class="py-4 pr-4 tabular-nums {{ $this->comparison[$key]['change'] < 0 ? 'text-red-600' : 'text-green-700' }}">
                                        {{ $this->comparison[$key]['change'] < 0 ? '−' : '+' }}M {{ number_format(abs($this->comparison[$key]['change']), 2) }}
                                    </td>
                                    <td class="py-4 tabular-nums">
                                        {{ $this->comparison[$key]['percentage'] === null ? '—' : number_format($this->comparison[$key]['percentage'], 1) . '%' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- CATEGORY BREAKDOWN --}}
    @if ($report === 'category_breakdown')
        @foreach (['income' => 'Income by category', 'expense' => 'Expenses by category'] as $type => $title)
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>

                @php
                    $categoryRows = $this->categoryBreakdown->get($type, collect());
                    $categoryMax = max((float) $categoryRows->max('total'), 1);
                    $categoryTotal = (float) $categoryRows->sum('total');
                @endphp

                @if ($categoryRows->isEmpty())
                    <p class="py-8 text-center text-sm text-gray-500">No {{ $type }} recorded for this period.</p>
                @else
                    <div class="mt-5 space-y-4">
                        @foreach ($categoryRows as $row)
                            <div>
                                <div class="mb-2 flex justify-between gap-3 text-sm">
                                    <span class="text-gray-700 dark:text-gray-300">{{ $row->category_name }}</span>
                                    <span class="whitespace-nowrap font-medium tabular-nums">M {{ number_format((float) $row->total, 2) }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                    <div class="{{ $type === 'income' ? 'bg-green-600' : 'bg-red-500' }} h-full rounded-full" style="width: {{ min(100, ((float) $row->total / $categoryMax) * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-5 overflow-x-auto border-t border-gray-100 pt-4 dark:border-gray-800">
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs uppercase text-gray-500">
                                <tr><th class="py-2">Category</th><th class="py-2 text-right">Amount</th><th class="py-2 text-right">Share</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($categoryRows as $row)
                                    <tr>
                                        <td class="py-3">{{ $row->category_name }}</td>
                                        <td class="py-3 text-right tabular-nums">M {{ number_format((float) $row->total, 2) }}</td>
                                        <td class="py-3 text-right tabular-nums">{{ $categoryTotal > 0 ? number_format(((float) $row->total / $categoryTotal) * 100, 1) : '0.0' }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="mt-4 text-right text-sm font-semibold">Total: M {{ number_format($categoryTotal, 2) }}</p>
                    </div>
                @endif
            </div>
        @endforeach
    @endif

    {{-- PAYMENT METHOD BREAKDOWN --}}
    @if ($report === 'payment_breakdown')
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Payment method breakdown</h2>
            <p class="mt-1 text-sm text-gray-500">See how much income and expense passed through each method.</p>

            @php
                $methods = $this->paymentBreakdown['methods'];
                $methodMax = max(collect($methods)->max('income'), collect($methods)->max('expenses'), 1);
            @endphp

            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500">
                        <tr><th class="py-3">Payment method</th><th class="py-3 text-right">Income</th><th class="py-3 text-right">Expenses</th><th class="py-3 text-right">Net</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($methods as $method)
                            <tr>
                                <td class="py-4 font-medium">{{ $method['label'] }}</td>
                                <td class="py-4 text-right tabular-nums text-green-700">M {{ number_format($method['income'], 2) }}</td>
                                <td class="py-4 text-right tabular-nums text-red-600">M {{ number_format($method['expenses'], 2) }}</td>
                                <td class="py-4 text-right tabular-nums font-medium">M {{ number_format($method['net'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h3 class="mt-8 font-semibold">Visual comparison</h3>
            <div class="mt-4 space-y-5">
                @foreach ($methods as $method)
                    <div>
                        <p class="mb-2 text-sm font-medium">{{ $method['label'] }}</p>
                        <div class="flex h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-full bg-green-600" style="width: {{ ($method['income'] / $methodMax) * 50 }}%"></div>
                            <div class="h-full bg-red-500" style="width: {{ ($method['expenses'] / $methodMax) * 50 }}%"></div>
                        </div>
                        <div class="mt-1 flex gap-4 text-xs text-gray-500">
                            <span>Income: M {{ number_format($method['income'], 2) }}</span>
                            <span>Expenses: M {{ number_format($method['expenses'], 2) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex gap-4 text-xs text-gray-500">
                <span><span class="mr-1 inline-block size-2 rounded-full bg-green-600"></span>Income</span>
                <span><span class="mr-1 inline-block size-2 rounded-full bg-red-500"></span>Expenses</span>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Bank breakdown</h2>
            <p class="mt-1 text-sm text-gray-500">Income and expenses recorded against each bank.</p>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500">
                        <tr><th class="py-3">Bank</th><th class="py-3 text-right">Income</th><th class="py-3 text-right">Expenses</th><th class="py-3 text-right">Net</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($this->paymentBreakdown['banks'] as $bank)
                            <tr>
                                <td class="py-4">{{ $bank['label'] }}</td>
                                <td class="py-4 text-right tabular-nums">M {{ number_format($bank['income'], 2) }}</td>
                                <td class="py-4 text-right tabular-nums">M {{ number_format($bank['expenses'], 2) }}</td>
                                <td class="py-4 text-right tabular-nums font-medium">M {{ number_format($bank['net'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- REGISTERS --}}
    @if (in_array($report, ['income_register', 'expense_register', 'payment_register'], true))
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Income total</p>
                <p class="mt-2 text-xl font-semibold tabular-nums">M {{ number_format($this->registerTotals['income'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Expense total</p>
                <p class="mt-2 text-xl font-semibold tabular-nums">M {{ number_format($this->registerTotals['expenses'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Net movement</p>
                <p class="mt-2 text-xl font-semibold tabular-nums">M {{ number_format($this->registerTotals['net'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Entries</p>
                <p class="mt-2 text-xl font-semibold tabular-nums">{{ number_format($this->registerTotals['count']) }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="space-y-4 border-b border-gray-200 p-4 dark:border-gray-700">
                <div>
                    <h2 class="font-semibold">
                        {{ $report === 'income_register' ? 'Income register' : ($report === 'expense_register' ? 'Expense register' : 'Payment method register') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">Search entries and review the running totals for this period.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Search entries..." icon="magnifying-glass" />

                    @if ($report === 'payment_register')
                        <flux:select wire:model.live="paymentMethodFilter">
                            <flux:select.option value="all">Choose payment method</flux:select.option>
                            <flux:select.option value="bank">Bank Account</flux:select.option>
                            <flux:select.option value="mpesa">M-Pesa</flux:select.option>
                            <flux:select.option value="ecocash">EcoCash</flux:select.option>
                        </flux:select>

                        @if ($paymentMethodFilter === 'bank')
                            <flux:select wire:model.live="bankFilter">
                                <flux:select.option value="all">All banks</flux:select.option>
                                <flux:select.option value="standard_lesotho_bank">Standard Lesotho Bank</flux:select.option>
                                <flux:select.option value="first_national_bank_lesotho">First National Bank Lesotho</flux:select.option>
                                <flux:select.option value="nedbank_lesotho">Nedbank Lesotho</flux:select.option>
                                <flux:select.option value="postbank_lesotho">PostBank Lesotho</flux:select.option>
                                <flux:select.option value="other">Other</flux:select.option>
                            </flux:select>
                        @endif
                    @endif

                    <flux:select wire:model.live="typeFilter">
                        <flux:select.option value="all">Income and expenses</flux:select.option>
                        <flux:select.option value="income">Income only</flux:select.option>
                        <flux:select.option value="expense">Expenses only</flux:select.option>
                    </flux:select>
                </div>
            </div>

            @if ($report === 'payment_register' && $this->paymentRegisterOpeningBalance !== null)
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-800/50">
                    <span class="text-gray-500">Opening balance:</span>
                    <span class="ml-2 font-semibold tabular-nums">M {{ number_format($this->paymentRegisterOpeningBalance, 2) }}</span>
                </div>
            @endif

            @if ($this->registerRows->isEmpty())
                <div class="px-5 py-12 text-center">
                    <p class="font-medium">No transactions found</p>
                    <p class="mt-1 text-sm text-gray-500">Try another date range or adjust your filters.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800/60">
                            <tr>
                                <th class="whitespace-nowrap px-4 py-3">Date</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Method / bank</th>
                                <th class="px-4 py-3 text-right">Income</th>
                                <th class="px-4 py-3 text-right">Expense</th>
                                <th class="px-4 py-3 text-right">Running total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($this->registerRows as $transaction)
                                <tr wire:key="report-transaction-{{ $transaction->id }}">
                                    <td class="whitespace-nowrap px-4 py-4 text-gray-500">{{ $transaction->date->format('d M Y') }}</td>
                                    <td class="min-w-48 px-4 py-4">
                                        <div class="font-medium">{{ $transaction->description }}</div>
                                        @if ($transaction->counterparty_name)
                                            <div class="mt-1 text-xs text-gray-500">{{ $transaction->counterparty_name }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4">{{ $transaction->category?->name ?? 'Uncategorised' }}</td>
                                    <td class="whitespace-nowrap px-4 py-4">
                                        <div>{{ ['bank' => 'Bank Account', 'mpesa' => 'M-Pesa', 'ecocash' => 'EcoCash'][$transaction->payment_method] ?? $transaction->payment_method }}</div>
                                        @if ($transaction->bank)
                                            <div class="mt-1 text-xs text-gray-500">{{ str($transaction->bank)->replace('_', ' ')->title() }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-right tabular-nums text-green-700">
                                        {{ $transaction->type === 'income' ? 'M ' . number_format((float) $transaction->amount, 2) : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-right tabular-nums text-red-600">
                                        {{ $transaction->type === 'expense' ? 'M ' . number_format((float) $transaction->amount, 2) : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-right tabular-nums font-medium">
                                        M {{ number_format((float) $transaction->running_total, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                    {{ $this->registerRows->links() }}
                </div>
            @endif
        </div>
    @endif

    <p class="text-xs leading-5 text-gray-500">
        Net result is income minus expenses for the selected period. It is not necessarily the same as the cash available in your accounts.
        Opening balances are shown separately and are not counted as income.
    </p>
</div>
