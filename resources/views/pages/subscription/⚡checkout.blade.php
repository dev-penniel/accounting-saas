<?php

use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use App\Models\User;
use App\Notifications\SystemNotification;


new class extends \Livewire\Component
{
    use WithFileUploads;

    public string $plan = 'monthly';

    public $proofOfPayment = null;

    #[Computed]
    public function plans(): array
    {
        return config('subscription.plans', []);
    }

    #[Computed]
    public function subscription()
    {
        return Auth::user()->activeSubscription;
    }

    #[Computed]
    public function pendingSubscription()
    {
        return Auth::user()
            ->subscriptions()
            ->where('status', 'pending')
            ->latest()
            ->first();
    }

    public function selectPlan(string $plan): void
    {
        if (! array_key_exists($plan, $this->plans)) {
            return;
        }

        $this->plan = $plan;

        $this->resetValidation();
    }

    public function submit(SubscriptionService $subscriptionService): void
    {
        $user = Auth::user();

        if ($user->hasActiveSubscription()) {
            $this->addError(
                'subscription',
                'You already have an active subscription.'
            );

            return;
        }

        $this->validate([
            'plan' => [
                'required',
                Rule::in(array_keys($this->plans)),
            ],

            'proofOfPayment' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ]);

        $subscriptionService->create(
            user: $user,
            plan: $this->plan,
            proofOfPayment: $this->proofOfPayment,
        );

        $this->reset('proofOfPayment');

        // Notifications to all general admins

        $admins = User::role('General Admin')->get();

        foreach ($admins as $admin) {
            $admin->notify(
                new SystemNotification(
                    title: 'New Subscription Request',
                    message: "{$user->name} submitted proof of payment for a {$this->plan} subscription.",
                    type: 'success',
                    url: route('subscriptions'),
                )
            );
        }

        unset($this->pendingSubscription);

        session()->flash(
            'success',
            'Your payment has been submitted successfully and is awaiting approval.'
        );
    }
};
?>

<div class="mx-auto max-w-5xl px-4 py-8">

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">
            Subscription
        </h1>

        <p class="mt-1 text-sm text-gray-500">
            Choose a plan and submit your payment.
        </p>
    </div>


    {{-- Success --}}
    @if (session('success'))
        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4">
            <p class="text-sm font-medium text-green-800">
                {{ session('success') }}
            </p>
        </div>
    @endif


    {{-- Validation --}}
    @error('subscription')
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4">
            <p class="text-sm font-medium text-red-800">
                {{ $message }}
            </p>
        </div>
    @enderror


    {{-- Active Subscription --}}
    @if ($this->subscription)

        <div class="mb-8 rounded-2xl border border-green-200 bg-green-50 p-6">

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                <div>

                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-green-500"></span>

                        <span class="text-sm font-semibold text-green-700">
                            Active subscription
                        </span>
                    </div>

                    <h2 class="mt-2 text-xl font-bold text-green-950">
                        {{ $this->plans[$this->subscription->plan]['name'] ?? ucfirst($this->subscription->plan) }}
                    </h2>

                    <p class="mt-1 text-sm text-green-700">
                        M{{ number_format($this->subscription->amount, 2) }}
                    </p>

                </div>

                <div class="sm:text-right">

                    <p class="text-xs font-medium uppercase tracking-wide text-green-600">
                        Expires
                    </p>

                    <p class="mt-1 font-semibold text-green-900">
                        {{ $this->subscription->expires_at?->format('d M Y') }}
                    </p>

                </div>

            </div>

        </div>

    @endif


    {{-- Pending Subscription --}}
    @if ($this->pendingSubscription)

        <div class="mb-8 rounded-2xl border border-yellow-200 bg-yellow-50 p-6">

            <div class="flex items-start gap-4">

                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-yellow-100">
                    <span class="text-yellow-700">!</span>
                </div>

                <div>

                    <h2 class="font-semibold text-yellow-900">
                        Payment awaiting approval
                    </h2>

                    <p class="mt-1 text-sm text-yellow-800">
                        Your
                        {{ $this->plans[$this->pendingSubscription->plan]['name'] ?? ucfirst($this->pendingSubscription->plan) }}
                        subscription payment is currently being reviewed.
                    </p>

                    <p class="mt-2 text-xs text-yellow-700">
                        Submitted
                        {{ $this->pendingSubscription->submitted_at?->format('d M Y \a\t H:i') }}
                    </p>

                </div>

            </div>

        </div>

    @endif


    {{-- Checkout --}}
    @if (! $this->subscription && ! $this->pendingSubscription)

        <div class="mb-8">

            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-900">
                    Choose your plan
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    Select the subscription that works best for you.
                </p>
            </div>


            <div class="grid gap-5 md:grid-cols-2">

                @foreach ($this->plans as $key => $planData)

                    <button
                        type="button"
                        wire:click="selectPlan('{{ $key }}')"
                        wire:key="plan-{{ $key }}"
                        class="relative rounded-2xl border-2 p-6 text-left transition
                        {{ $plan === $key
                            ? 'border-primary bg-primary/5'
                            : 'border-gray-200 bg-white hover:border-gray-300' }}"
                    >

                        @if ($plan === $key)

                            <div class="absolute right-5 top-5">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">
                                    ✓
                                </span>
                            </div>

                        @endif

                        <p class="text-sm font-medium text-gray-500">
                            {{ $planData['name'] }}
                        </p>

                        <div class="mt-2 flex items-baseline gap-1">

                            <span class="text-3xl font-bold text-gray-900">
                                M{{ number_format($planData['amount'], 2) }}
                            </span>

                            <span class="text-sm text-gray-500">
                                /{{ $key === 'monthly' ? 'month' : 'year' }}
                            </span>

                        </div>

                        <p class="mt-4 text-sm text-gray-600">
                            {{ $key === 'monthly'
                                ? 'Flexible monthly access.'
                                : 'Full-year access at a discounted rate.' }}
                        </p>

                    </button>

                @endforeach

            </div>

        </div>


        {{-- Payment --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">

            <div class="mb-6">

                <h2 class="text-lg font-semibold text-gray-900">
                    Make your payment
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    Complete the payment and upload your proof below.
                </p>

            </div>


            {{-- Payment Details --}}
            <div class="rounded-xl bg-gray-50 p-5">

                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Payment details
                </p>

                <div class="mt-4 space-y-3 text-sm">

                    <div class="flex justify-between gap-4">
                        <span class="text-gray-500">
                            Plan
                        </span>

                        <span class="font-medium text-gray-900">
                            {{ $this->plans[$plan]['name'] }}
                        </span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-gray-500">
                            Amount
                        </span>

                        <span class="font-semibold text-gray-900">
                            M{{ number_format($this->plans[$plan]['amount'], 2) }}
                        </span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-gray-500">
                            Reference
                        </span>

                        <span class="font-medium text-gray-900">
                            {{ Auth::user()->email }}
                        </span>
                    </div>

                </div>

            </div>


            {{-- Upload --}}
            <form
                wire:submit="submit"
                class="mt-6"
            >

                <label
                    for="proofOfPayment"
                    class="block text-sm font-medium text-gray-900"
                >
                    Proof of payment
                </label>

                <input
                    id="proofOfPayment"
                    type="file"
                    wire:model="proofOfPayment"
                    accept=".jpg,.jpeg,.png,.pdf"
                    class="mt-2 block w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm"
                >

                @error('proofOfPayment')
                    <p class="mt-2 text-sm text-red-600">
                        {{ $message }}
                    </p>
                @enderror

                <div
                    wire:loading
                    wire:target="proofOfPayment"
                    class="mt-2 text-sm text-gray-500"
                >
                    Uploading file...
                </div>

                <p class="mt-2 text-xs text-gray-500">
                    JPG, PNG or PDF. Maximum size: 5MB.
                </p>


                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="submit"
                    class="mt-6 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                >

                    <span wire:loading.remove wire:target="submit">
                        Submit payment
                    </span>

                    <span wire:loading wire:target="submit">
                        Submitting...
                    </span>

                </button>

            </form>

        </div>

    @endif

</div>