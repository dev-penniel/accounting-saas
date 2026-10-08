<?php

use Livewire\Component;

new class extends Component
{
    public function getUnreadCountProperty()
    {
        return auth()->user()
            ->unreadNotifications()
            ->count();
    }
};
?>

<flux:button
    href="{{ route('c-notifications') }}"
    variant="ghost"
    :current="request()->routeIs('c-notifications')"
    wire:navigate
>
    <span class="flex items-center gap-2">
        Notifications

        @if ($this->unreadCount > 0)
            <flux:badge size="sm" color="green" variant="danger">
                {{ $this->unreadCount }}
            </flux:badge>
        @endif
    </span>
</flux:button>