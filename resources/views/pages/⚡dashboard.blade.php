<?php

use App\Models\Subscription;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function pendingPayments()
    {
        return Subscription::query()
            ->where('status', 'pending')
            ->count();
    }

    #[Computed]
    public function activeSubscriptions()
    {
        return Subscription::query()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->count();
    }

    #[Computed]
    public function monthlyRevenue()
    {
        return Subscription::query()
            ->where('plan', 'monthly')
            ->whereIn('status', ['active', 'expired'])
            ->sum('amount');
    }

    #[Computed]
    public function annualRevenue()
    {
        return Subscription::query()
            ->where('plan', 'annual')
            ->whereIn('status', ['active', 'expired'])
            ->sum('amount');
    }

    #[Computed]
    public function totalSubscribers()
    {
        return Subscription::query()
            ->whereIn('status', ['active', 'expired'])
            ->distinct('user_id')
            ->count('user_id');
    }

    #[Computed]
    public function recentSubscriptions()
    {
        return Subscription::query()
            ->with('user')
            ->latest()
            ->take(5)
            ->get();
    }

    #[Computed]
    public function recentActivity()
    {
        return Subscription::query()
            ->with('user')
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->take(5)
            ->get();
    }
};
?>

<div>

    <div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Header --}}
        <div>
            <flux:heading size="xl">
                Subscription Dashboard
            </flux:heading>

            <flux:text class="mt-1">
                Here's an overview of your subscription system.
            </flux:text>
        </div>

        {{-- Statistics --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

            {{-- Pending Payments --}}
            <flux:card>
                <div class="flex items-center justify-between">

                    <div>
                        <flux:text>
                            Pending Payments
                        </flux:text>

                        <flux:heading size="xl" class="mt-2">
                            {{ $this->pendingPayments }}
                        </flux:heading>
                    </div>

                    <div class="flex size-11 items-center justify-center rounded-lg bg-yellow-50 dark:bg-yellow-950">
                        <flux:icon
                            name="clock"
                            class="size-6 text-yellow-600 dark:text-yellow-400"
                        />
                    </div>

                </div>

                <flux:text class="mt-4 text-sm">
                    Awaiting approval
                </flux:text>
            </flux:card>

            {{-- Active Subscriptions --}}
            <flux:card>
                <div class="flex items-center justify-between">

                    <div>
                        <flux:text>
                            Active Subscriptions
                        </flux:text>

                        <flux:heading size="xl" class="mt-2">
                            {{ $this->activeSubscriptions }}
                        </flux:heading>
                    </div>

                    <div class="flex size-11 items-center justify-center rounded-lg bg-green-50 dark:bg-green-950">
                        <flux:icon
                            name="check-circle"
                            class="size-6 text-green-600 dark:text-green-400"
                        />
                    </div>

                </div>

                <flux:text class="mt-4 text-sm">
                    Currently active
                </flux:text>
            </flux:card>

            {{-- Monthly Revenue --}}
            <flux:card>
                <div class="flex items-center justify-between">

                    <div>
                        <flux:text>
                            Monthly Revenue
                        </flux:text>

                        <flux:heading size="xl" class="mt-2">
                            M{{ number_format($this->monthlyRevenue, 2) }}
                        </flux:heading>
                    </div>

                    <div class="flex size-11 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-950">
                        <flux:icon
                            name="arrow-trending-up"
                            class="size-6 text-blue-600 dark:text-blue-400"
                        />
                    </div>

                </div>

                <flux:text class="mt-4 text-sm">
                    Monthly subscriptions
                </flux:text>
            </flux:card>

            {{-- Annual Revenue --}}
            <flux:card>
                <div class="flex items-center justify-between">

                    <div>
                        <flux:text>
                            Annual Revenue
                        </flux:text>

                        <flux:heading size="xl" class="mt-2">
                            M{{ number_format($this->annualRevenue, 2) }}
                        </flux:heading>
                    </div>

                    <div class="flex size-11 items-center justify-center rounded-lg bg-purple-50 dark:bg-purple-950">
                        <flux:icon
                            name="chart-bar"
                            class="size-6 text-purple-600 dark:text-purple-400"
                        />
                    </div>

                </div>

                <flux:text class="mt-4 text-sm">
                    Annual subscriptions
                </flux:text>
            </flux:card>

        </div>

        {{-- Main Content --}}
        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Recent Subscriptions --}}
            <flux:card class="lg:col-span-2">

                <div class="flex items-center justify-between">

                    <div>
                        <flux:heading size="lg">
                            Recent Subscriptions
                        </flux:heading>

                        <flux:text class="mt-1">
                            Latest subscription activity.
                        </flux:text>
                    </div>

                    <flux:button
                        variant="ghost"
                        size="sm"
                        href="{{ route('subscriptions') }}"
                    >
                        View all
                    </flux:button>

                </div>

                <div class="mt-6 overflow-x-auto">

                    <flux:table>

                        <flux:table.columns>

                            <flux:table.column>
                                Subscriber
                            </flux:table.column>

                            <flux:table.column>
                                Plan
                            </flux:table.column>

                            <flux:table.column>
                                Amount
                            </flux:table.column>

                            <flux:table.column>
                                Status
                            </flux:table.column>

                            <flux:table.column>
                                Submitted
                            </flux:table.column>

                        </flux:table.columns>

                        <flux:table.rows>

                            @forelse($this->recentSubscriptions as $subscription)

                                <flux:table.row>

                                    <flux:table.cell>

                                        <div class="flex items-center gap-3">

                                            <flux:avatar
                                                initials="{{ strtoupper(substr($subscription->user->name, 0, 2)) }}"
                                                size="sm"
                                            />

                                            <div>

                                                <flux:text class="font-medium">
                                                    {{ $subscription->user->name }}
                                                </flux:text>

                                                <flux:text size="sm" class="text-zinc-500">
                                                    {{ $subscription->user->email }}
                                                </flux:text>

                                            </div>

                                        </div>

                                    </flux:table.cell>

                                    <flux:table.cell>

                                        <flux:badge
                                            size="sm"
                                            :color="$subscription->plan === 'annual' ? 'purple' : 'blue'"
                                        >
                                            {{ ucfirst($subscription->plan) }}
                                        </flux:badge>

                                    </flux:table.cell>

                                    <flux:table.cell>

                                        M{{ number_format($subscription->amount, 2) }}

                                    </flux:table.cell>

                                    <flux:table.cell>

                                        @if($subscription->status === 'active')

                                            <flux:badge color="green">
                                                Active
                                            </flux:badge>

                                        @elseif($subscription->status === 'pending')

                                            <flux:badge color="yellow">
                                                Pending
                                            </flux:badge>

                                        @elseif($subscription->status === 'expired')

                                            <flux:badge color="zinc">
                                                Expired
                                            </flux:badge>

                                        @elseif($subscription->status === 'rejected')

                                            <flux:badge color="red">
                                                Rejected
                                            </flux:badge>

                                        @else

                                            <flux:badge color="zinc">
                                                {{ ucfirst($subscription->status) }}
                                            </flux:badge>

                                        @endif

                                    </flux:table.cell>

                                    <flux:table.cell>

                                        @if($subscription->submitted_at)

                                            {{ $subscription->submitted_at->diffForHumans() }}

                                        @else

                                            —

                                        @endif

                                    </flux:table.cell>

                                </flux:table.row>

                            @empty

                                <flux:table.row>

                                    <flux:table.cell colspan="5">

                                        <div class="py-8 text-center">

                                            <flux:icon
                                                name="credit-card"
                                                class="mx-auto size-8 text-zinc-400"
                                            />

                                            <flux:text class="mt-2">
                                                No subscriptions yet.
                                            </flux:text>

                                        </div>

                                    </flux:table.cell>

                                </flux:table.row>

                            @endforelse

                        </flux:table.rows>

                    </flux:table>

                </div>

            </flux:card>

            {{-- Quick Actions --}}
            <flux:card>

                <flux:heading size="lg">
                    Quick Actions
                </flux:heading>

                <flux:text class="mt-1">
                    Manage your subscription system.
                </flux:text>

                <div class="mt-6 flex flex-col gap-3">

                    <flux:button
                        variant="primary"
                        icon="credit-card"
                        class="w-full"
                        href="{{ route('subscriptions') }}"
                    >
                        Manage Subscriptions
                    </flux:button>

                    <flux:button
                        variant="ghost"
                        icon="clock"
                        class="w-full"
                        href="{{ route('subscriptions') }}"
                    >
                        Review Pending Payments
                    </flux:button>

                    <flux:button
                        variant="ghost"
                        icon="users"
                        class="w-full"
                        href="{{ route('subscriptions') }}"
                    >
                        View Subscribers
                    </flux:button>

                    <flux:button
                        variant="ghost"
                        icon="chart-bar"
                        class="w-full"
                        href="{{ route('subscriptions') }}"
                    >
                        Subscription Reports
                    </flux:button>

                </div>

            </flux:card>

        </div>

        {{-- Bottom Section --}}
        <div class="grid gap-6 lg:grid-cols-2">

            {{-- Recent Subscription Activity --}}
            <flux:card>

                <flux:heading size="lg">
                    Recent Activity
                </flux:heading>

                <flux:text class="mt-1">
                    Latest subscription events.
                </flux:text>

                <div class="mt-6 space-y-5">

                    @forelse($this->recentActivity as $subscription)

                        <div class="flex gap-3">

                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-full
                                @if($subscription->status === 'active')
                                    bg-green-50 dark:bg-green-950
                                @elseif($subscription->status === 'pending')
                                    bg-yellow-50 dark:bg-yellow-950
                                @elseif($subscription->status === 'rejected')
                                    bg-red-50 dark:bg-red-950
                                @else
                                    bg-blue-50 dark:bg-blue-950
                                @endif"
                            >

                                @if($subscription->status === 'active')

                                    <flux:icon
                                        name="check-circle"
                                        class="size-5 text-green-600"
                                    />

                                @elseif($subscription->status === 'pending')

                                    <flux:icon
                                        name="clock"
                                        class="size-5 text-yellow-600"
                                    />

                                @elseif($subscription->status === 'rejected')

                                    <flux:icon
                                        name="x-circle"
                                        class="size-5 text-red-600"
                                    />

                                @else

                                    <flux:icon
                                        name="credit-card"
                                        class="size-5 text-blue-600"
                                    />

                                @endif

                            </div>

                            <div>

                                <flux:text>

                                    @if($subscription->status === 'pending')

                                        <strong>
                                            Payment submitted
                                        </strong>

                                    @elseif($subscription->status === 'active')

                                        <strong>
                                            Subscription approved
                                        </strong>

                                    @elseif($subscription->status === 'rejected')

                                        <strong>
                                            Payment rejected
                                        </strong>

                                    @else

                                        <strong>
                                            Subscription activity
                                        </strong>

                                    @endif

                                </flux:text>

                                <flux:text size="sm" class="text-zinc-500">

                                    {{ $subscription->user->name }}
                                    —
                                    {{ ucfirst($subscription->plan) }}
                                    subscription

                                </flux:text>

                                <flux:text size="sm" class="mt-1 text-zinc-400">

                                    {{ $subscription->submitted_at?->diffForHumans() }}

                                </flux:text>

                            </div>

                        </div>

                    @empty

                        <flux:text class="py-4 text-zinc-500">
                            No subscription activity yet.
                        </flux:text>

                    @endforelse

                </div>

            </flux:card>

            {{-- Subscription Overview --}}
            <flux:card>

                <flux:heading size="lg">
                    Subscription Overview
                </flux:heading>

                <flux:text class="mt-1">
                    Current state of your subscription business.
                </flux:text>

                <div class="mt-6 space-y-4">

                    {{-- Total Subscribers --}}
                    <div class="flex items-center justify-between">

                        <div class="flex items-center gap-3">

                            <div class="flex size-9 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-950">

                                <flux:icon
                                    name="users"
                                    class="size-5 text-blue-600 dark:text-blue-400"
                                />

                            </div>

                            <flux:text>
                                Total Subscribers
                            </flux:text>

                        </div>

                        <flux:heading size="lg">
                            {{ $this->totalSubscribers }}
                        </flux:heading>

                    </div>

                    {{-- Monthly Plan --}}
                    <div class="flex items-center justify-between">

                        <div class="flex items-center gap-3">

                            <div class="flex size-9 items-center justify-center rounded-full bg-green-50 dark:bg-green-950">

                                <flux:icon
                                    name="arrow-path"
                                    class="size-5 text-green-600 dark:text-green-400"
                                />

                            </div>

                            <flux:text>
                                Monthly Plan
                            </flux:text>

                        </div>

                        <flux:badge color="blue">
                            M80 / month
                        </flux:badge>

                    </div>

                    {{-- Annual Plan --}}
                    <div class="flex items-center justify-between">

                        <div class="flex items-center gap-3">

                            <div class="flex size-9 items-center justify-center rounded-full bg-purple-50 dark:bg-purple-950">

                                <flux:icon
                                    name="calendar"
                                    class="size-5 text-purple-600 dark:text-purple-400"
                                />

                            </div>

                            <flux:text>
                                Annual Plan
                            </flux:text>

                        </div>

                        <flux:badge color="purple">
                            M800 / year
                        </flux:badge>

                    </div>

                    {{-- Pending --}}
                    <div class="flex items-center justify-between">

                        <div class="flex items-center gap-3">

                            <div class="flex size-9 items-center justify-center rounded-full bg-yellow-50 dark:bg-yellow-950">

                                <flux:icon
                                    name="clock"
                                    class="size-5 text-yellow-600 dark:text-yellow-400"
                                />

                            </div>

                            <flux:text>
                                Awaiting Approval
                            </flux:text>

                        </div>

                        <flux:badge color="yellow">
                            {{ $this->pendingPayments }}
                        </flux:badge>

                    </div>

                </div>

            </flux:card>

        </div>

    </div>

</div>