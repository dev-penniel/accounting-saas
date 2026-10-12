<?php

use Livewire\Component;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Notifications\SystemNotification;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Storage;

new class extends Component
{
    use WithPagination;

    public $search = '';

    public $status = '';

    public $subscriptionId;

    public $subscriptionUser;

    public $deleteName;

    public $selectedSubscription;

    public function mount()
    {
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatus()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->reset([
            'search',
            'status',
        ]);

        $this->resetPage();
    }

    #[Computed]
    public function subscriptions()
    {
        return Subscription::query()
            ->with([
                'user',
                'approver',
            ])
            ->when($this->search, function ($query) {
                $query->whereHas('user', function ($userQuery) {
                    $userQuery
                        ->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->status, function ($query) {
                $query->where('status', $this->status);
            })
            ->latest()
            ->paginate(10);
    }

    public function viewSubscription($id)
    {
        $this->selectedSubscription = Subscription::with([
            'user',
            'approver',
        ])->findOrFail($id);

        $this->modal('view-subscription')->show();
    }

    public function confirmApprove($id)
    {
        $this->subscriptionId = $id;

        $subscription = Subscription::with('user')->findOrFail($id);

        $this->subscriptionUser = $subscription->user->name;

        $this->modal('approve-subscription')->show();
    }

    public function approve(SubscriptionService $subscriptionService)
    {
        $subscription = Subscription::with('user')->findOrFail(
            $this->subscriptionId
        );

        if ($subscription->status !== 'pending') {
            $this->modal('approve-subscription')->close();

            return;
        }

        $subscriptionService->approve(
            subscription: $subscription,
            admin: auth()->user(),
        );

        $subscription->user->notify(
            new SystemNotification(
                title: 'Subscription Approved',
                message: 'Your subscription has been approved and is now active.',
                type: 'success'
            )
        );

        $this->modal('approve-subscription')->close();

        $this->reset([
            'subscriptionId',
            'subscriptionUser',
        ]);

        $this->dispatch('subscription-approved');
    }

    public function confirmReject($id)
    {
        $this->subscriptionId = $id;

        $subscription = Subscription::with('user')->findOrFail($id);

        $this->subscriptionUser = $subscription->user->name;

        $this->modal('reject-subscription')->show();
    }

    public function reject(SubscriptionService $subscriptionService)
    {
        $subscription = Subscription::with('user')->findOrFail(
            $this->subscriptionId
        );

        if ($subscription->status !== 'pending') {
            $this->modal('reject-subscription')->close();

            return;
        }

        $subscriptionService->reject($subscription);

        $subscription->user->notify(
            new SystemNotification(
                title: 'Subscription Rejected',
                message: 'Your proof of payment was rejected. Please contact support or submit a new payment proof.',
                type: 'error',
                url: route('subscription'),
            )
        );

        $this->modal('reject-subscription')->close();

        $this->reset([
            'subscriptionId',
            'subscriptionUser',
        ]);

        $this->dispatch('subscription-rejected');
    }
};

?>

<div>

    {{-- Approve confirmation modal --}}
    <flux:modal name="approve-subscription" class="min-w-[22rem]">

        <div class="space-y-6">

            <div>
                <flux:heading size="lg">
                    Approve Subscription
                </flux:heading>

                <flux:text class="mt-2">
                    You're about to approve the subscription for
                    <strong>{{ $subscriptionUser }}</strong>.
                    <br><br>
                    This will activate their subscription and start their
                    subscription period.
                </flux:text>
            </div>

            <div class="flex gap-2">

                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">
                        Cancel
                    </flux:button>
                </flux:modal.close>

                <flux:button
                    type="button"
                    variant="primary"
                    wire:click="approve"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="approve">
                        Approve Subscription
                    </span>

                    <span wire:loading wire:target="approve">
                        Approving...
                    </span>
                </flux:button>

            </div>

        </div>

    </flux:modal>


    {{-- Reject confirmation modal --}}
    <flux:modal name="reject-subscription" class="min-w-[22rem]">

        <div class="space-y-6">

            <div>

                <flux:heading size="lg">
                    Reject Subscription
                </flux:heading>

                <flux:text class="mt-2">
                    You're about to reject the proof of payment submitted by
                    <strong>{{ $subscriptionUser }}</strong>.
                    <br><br>
                    The user will need to submit a new payment proof.
                </flux:text>

            </div>

            <div class="flex gap-2">

                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">
                        Cancel
                    </flux:button>
                </flux:modal.close>

                <flux:button
                    type="button"
                    variant="danger"
                    wire:click="reject"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="reject">
                        Reject Subscription
                    </span>

                    <span wire:loading wire:target="reject">
                        Rejecting...
                    </span>
                </flux:button>

            </div>

        </div>

    </flux:modal>


    {{-- View subscription modal --}}
    <flux:modal name="view-subscription" class="md:w-full">

        @if ($selectedSubscription)

            <div class="space-y-6">

                <div>

                    <flux:heading size="lg">
                        Subscription Details
                    </flux:heading>

                    <flux:text class="mt-2">
                        Complete information about this subscription.
                    </flux:text>

                </div>

                <flux:separator variant="subtle" />

                <div class="grid md:grid-cols-2 gap-6">

                    <div class="space-y-4">

                        <div>
                            <flux:text size="sm">
                                User
                            </flux:text>

                            <flux:heading size="sm">
                                {{ $selectedSubscription->user->name }}
                            </flux:heading>
                        </div>

                        <div>
                            <flux:text size="sm">
                                Email
                            </flux:text>

                            <flux:heading size="sm">
                                {{ $selectedSubscription->user->email }}
                            </flux:heading>
                        </div>

                        <div>
                            <flux:text size="sm">
                                Plan
                            </flux:text>

                            <flux:heading size="sm">
                                {{ ucfirst($selectedSubscription->plan) }}
                            </flux:heading>
                        </div>

                        <div>
                            <flux:text size="sm">
                                Amount
                            </flux:text>

                            <flux:heading size="sm">
                                M{{ number_format($selectedSubscription->amount, 2) }}
                            </flux:heading>
                        </div>

                    </div>


                    <div class="space-y-4">

                        <div>
                            <flux:text size="sm">
                                Status
                            </flux:text>

                            @if ($selectedSubscription->status === 'active')

                                <flux:badge color="green">
                                    Active
                                </flux:badge>

                            @elseif ($selectedSubscription->status === 'pending')

                                <flux:badge color="yellow">
                                    Pending
                                </flux:badge>

                            @elseif ($selectedSubscription->status === 'rejected')

                                <flux:badge color="red">
                                    Rejected
                                </flux:badge>

                            @elseif ($selectedSubscription->status === 'expired')

                                <flux:badge color="zinc">
                                    Expired
                                </flux:badge>

                            @else

                                <flux:badge color="zinc">
                                    {{ ucfirst($selectedSubscription->status) }}
                                </flux:badge>

                            @endif

                        </div>


                        <div>
                            <flux:text size="sm">
                                Submitted
                            </flux:text>

                            <flux:heading size="sm">
                                {{ $selectedSubscription->submitted_at?->format('d M Y H:i') ?? '—' }}
                            </flux:heading>
                        </div>


                        <div>
                            <flux:text size="sm">
                                Starts
                            </flux:text>

                            <flux:heading size="sm">
                                {{ $selectedSubscription->starts_at?->format('d M Y H:i') ?? '—' }}
                            </flux:heading>
                        </div>


                        <div>
                            <flux:text size="sm">
                                Expires
                            </flux:heading>

                            <flux:heading size="sm">
                                {{ $selectedSubscription->expires_at?->format('d M Y H:i') ?? '—' }}
                            </flux:heading>
                        </div>

                    </div>

                </div>


                @if ($selectedSubscription->proof_of_payment)

                    <flux:separator variant="subtle" />

                    <div>

                        <flux:heading size="sm">
                            Proof of Payment
                        </flux:heading>

                        <flux:text class="mt-1 mb-3">
                            Payment document submitted by the user.
                        </flux:text>

                        <flux:button
                            href="{{ Storage::disk('public')->url($selectedSubscription->proof_of_payment) }}"
                            target="_blank"
                            icon="document-text"
                            variant="primary"
                            size="sm"
                        >
                            View Proof of Payment
                        </flux:button>

                    </div>

                @endif


                @if ($selectedSubscription->approved_at)

                    <flux:separator variant="subtle" />

                    <div>

                        <flux:heading size="sm">
                            Approval Information
                        </flux:heading>

                        <div class="mt-3 space-y-2">

                            <flux:text>
                                Approved:
                                {{ $selectedSubscription->approved_at->format('d M Y H:i') }}
                            </flux:text>

                            @if ($selectedSubscription->approver)

                                <flux:text>
                                    Approved by:
                                    {{ $selectedSubscription->approver->name }}
                                </flux:text>

                            @endif

                        </div>

                    </div>

                @endif


                <div class="flex">

                    <flux:spacer />

                    <flux:modal.close>
                        <flux:button variant="ghost">
                            Close
                        </flux:button>
                    </flux:modal.close>

                </div>

            </div>

        @endif

    </flux:modal>


    {{-- Page heading --}}
    <div class="relative mb-6 w-full">

        <div class="flex justify-between items-center">

            <div>

                <flux:heading size="xl" level="1">
                    {{ __('Subscriptions') }}
                </flux:heading>

                <flux:breadcrumbs class="mb-4 mt-2">

                    <flux:breadcrumbs.item
                        href="{{ route('dashboard') }}"
                    >
                        Home
                    </flux:breadcrumbs.item>

                    <flux:breadcrumbs.item>
                        Subscriptions
                    </flux:breadcrumbs.item>

                </flux:breadcrumbs>

            </div>

        </div>

        <flux:separator variant="subtle" />

    </div>


    {{-- Search and filters --}}
    <div>

        <div class="flex flex-wrap justify-between items-center gap-4 mb-5">

            <div class="w-full md:w-72">

                <flux:input
                    wire:model.live="search"
                    type="text"
                    placeholder="Search user or email"
                />

            </div>


            <div class="flex items-center gap-2">

                <flux:select wire:model.live="status">

                    <flux:select.option value="">
                        All Statuses
                    </flux:select.option>

                    <flux:select.option value="pending">
                        Pending
                    </flux:select.option>

                    <flux:select.option value="active">
                        Active
                    </flux:select.option>

                    <flux:select.option value="expired">
                        Expired
                    </flux:select.option>

                    <flux:select.option value="rejected">
                        Rejected
                    </flux:select.option>

                    <flux:select.option value="cancelled">
                        Cancelled
                    </flux:select.option>

                </flux:select>


                @if ($search || $status)

                    <flux:button
                        variant="ghost"
                        size="sm"
                        wire:click="resetFilters"
                    >
                        Clear
                    </flux:button>

                @endif

            </div>

        </div>


        {{-- Subscriptions table --}}
        <flux:table :paginate="$this->subscriptions">

            <flux:table.columns>

                <flux:table.column sticky class="bg-white dark:bg-zinc-900">
                    No:
                </flux:table.column>

                <flux:table.column>
                    User
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
                    POP
                </flux:table.column>

                <flux:table.column>
                    Submitted
                </flux:table.column>

                <flux:table.column>
                    Expires
                </flux:table.column>

                <flux:table.column>
                </flux:table.column>

            </flux:table.columns>


            <flux:table.rows>

                @forelse ($this->subscriptions as $index => $subscription)

                    <flux:table.row wire:key="subscription-{{ $subscription->id }}">

                        <flux:table.cell
                            sticky
                            class="bg-white dark:bg-zinc-900"
                        >
                            {{
                                ($this->subscriptions->currentPage() - 1)
                                * $this->subscriptions->perPage()
                                + $index
                                + 1
                            }}
                        </flux:table.cell>


                        <flux:table.cell>

                            <div class="flex flex-col">

                                <span class="font-medium">
                                    {{ $subscription->user->name }}
                                </span>

                                <span class="text-xs text-zinc-500">
                                    {{ $subscription->user->email }}
                                </span>

                            </div>

                        </flux:table.cell>


                        <flux:table.cell>

                            <span class="capitalize">
                                {{ $subscription->plan }}
                            </span>

                        </flux:table.cell>


                        <flux:table.cell>

                            M{{ number_format($subscription->amount, 2) }}

                        </flux:table.cell>


                        <flux:table.cell>

                            @if ($subscription->status === 'active')

                                <flux:badge color="green">
                                    Active
                                </flux:badge>

                            @elseif ($subscription->status === 'pending')

                                <flux:badge color="yellow">
                                    Pending
                                </flux:badge>

                            @elseif ($subscription->status === 'rejected')

                                <flux:badge color="red">
                                    Rejected
                                </flux:badge>

                            @elseif ($subscription->status === 'expired')

                                <flux:badge color="zinc">
                                    Expired
                                </flux:badge>

                            @else

                                <flux:badge color="zinc">
                                    {{ ucfirst($subscription->status) }}
                                </flux:badge>

                            @endif

                        </flux:table.cell>


                        <flux:table.cell>

                            @if ($subscription->proof_of_payment)

                                <flux:badge color="green">
                                    Submitted
                                </flux:badge>

                            @else

                                <flux:badge color="zinc">
                                    None
                                </flux:badge>

                            @endif

                        </flux:table.cell>


                        <flux:table.cell>

                            {{ $subscription->submitted_at?->diffForHumans() ?? '—' }}

                        </flux:table.cell>


                        <flux:table.cell>

                            @if ($subscription->expires_at)

                                {{ $subscription->expires_at->format('d M Y') }}

                            @else

                                —

                            @endif

                        </flux:table.cell>


                        <flux:table.cell
                            sticky
                            class="py-0 bg-white dark:bg-zinc-900"
                        >

                            <flux:dropdown align="end">

                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="ellipsis-horizontal"
                                />

                                <flux:menu>

                                    {{-- View --}}
                                    <flux:menu.item
                                        icon="eye"
                                        wire:click="viewSubscription({{ $subscription->id }})"
                                    >
                                        View
                                    </flux:menu.item>


                                    {{-- View POP --}}
                                    @if ($subscription->proof_of_payment)

                                        <flux:menu.item
                                            icon="document-text"
                                            href="{{ Storage::disk('public')->url($subscription->proof_of_payment) }}"
                                            target="_blank"
                                        >
                                            View POP
                                        </flux:menu.item>

                                    @endif


                                    {{-- Approve --}}
                                    @if ($subscription->status === 'pending')

                                        <flux:menu.separator />

                                        <flux:menu.item
                                            icon="check"
                                            wire:click="confirmApprove({{ $subscription->id }})"
                                        >
                                            Approve
                                        </flux:menu.item>


                                        {{-- Reject --}}
                                        <flux:menu.item
                                            variant="danger"
                                            icon="x-mark"
                                            wire:click="confirmReject({{ $subscription->id }})"
                                        >
                                            Reject
                                        </flux:menu.item>

                                    @endif

                                </flux:menu>

                            </flux:dropdown>

                        </flux:table.cell>

                    </flux:table.row>

                @empty

                    <flux:table.row>

                        <flux:table.cell colspan="9">

                            <div class="flex min-h-80 flex-col items-center justify-center text-center">

                                <div class="mb-4 flex size-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">

                                    <flux:icon
                                        name="credit-card"
                                        class="size-6 text-zinc-500"
                                    />

                                </div>

                                <flux:heading size="lg">
                                    No subscriptions found
                                </flux:heading>

                                <flux:text class="mt-1 max-w-sm text-center">

                                    There are no subscriptions matching your
                                    current search or filter.

                                </flux:text>

                            </div>

                        </flux:table.cell>

                    </flux:table.row>

                @endforelse

            </flux:table.rows>

        </flux:table>

    </div>

</div>