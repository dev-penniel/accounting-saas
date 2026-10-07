<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SubscriptionService
{
    /**
     * Create a new subscription request.
     */
    public function create(
        User $user,
        string $plan,
        ?UploadedFile $proofOfPayment = null
    ): Subscription {
        $planConfig = $this->getPlan($plan);

        return DB::transaction(function () use (
            $user,
            $plan,
            $planConfig,
            $proofOfPayment
        ) {
            // Prevent multiple pending subscriptions.
            $user->subscriptions()
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                ]);

            $proofPath = null;

            if ($proofOfPayment) {
                $proofPath = $proofOfPayment->store(
                    'subscriptions/proofs',
                    'public'
                );
            }

            return $user->subscriptions()->create([
                'plan' => $plan,
                'amount' => $planConfig['amount'],
                'status' => 'pending',
                'proof_of_payment' => $proofPath,
                'submitted_at' => $proofOfPayment ? now() : null,
            ]);
        });
    }

    /**
     * Approve a subscription.
     */
    public function approve(
        Subscription $subscription,
        User $admin
    ): Subscription {
        return DB::transaction(function () use ($subscription, $admin) {
            // Don't approve an already active subscription.
            if ($subscription->status === 'active') {
                return $subscription;
            }

            $plan = $this->getPlan($subscription->plan);

            // Cancel any other active subscriptions.
            $subscription->user
                ->subscriptions()
                ->where('id', '!=', $subscription->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'cancelled',
                ]);

            $subscription->update([
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => now()->addDays($plan['days']),
                'approved_at' => now(),
                'approved_by' => $admin->id,
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Reject a subscription.
     */
    public function reject(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => 'rejected',
        ]);

        return $subscription->fresh();
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => 'cancelled',
        ]);

        return $subscription->fresh();
    }

    /**
     * Expire subscriptions whose expiry date has passed.
     */
    public function expireSubscriptions(): int
    {
        return Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => 'expired',
            ]);
    }

    /**
     * Get a configured subscription plan.
     */
    public function getPlan(string $plan): array
    {
        $plans = config('subscription.plans');

        if (! isset($plans[$plan])) {
            throw new \InvalidArgumentException(
                "Invalid subscription plan: {$plan}"
            );
        }

        return $plans[$plan];
    }

    /**
     * Get all available plans.
     */
    public function getPlans(): array
    {
        return config('subscription.plans', []);
    }
}