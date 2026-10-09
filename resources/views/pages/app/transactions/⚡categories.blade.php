
<?php

use App\Models\Category;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::app.frontend')]
class extends Component
{
    public bool $showForm = false;

    public ?int $editingCategoryId = null;

    public string $type = 'expense';
    public string $name = '';
    public string $parent_id = '';

    public string $search = '';
    public string $visibilityFilter = 'visible';

    public function mount(): void
    {
        //
    }

    public function updatedType(): void
    {
        $this->resetPageState();
    }

    private function resetPageState(): void
    {
        $this->resetValidation();
        $this->reset('search');
        unset($this->categories, $this->parentCategories, $this->summary);
    }

    #[Computed]
    public function categories()
    {
        return auth()->user()->categories()
            ->with('parent')
            ->withCount('transactions')
            ->where('type', $this->type)
            ->when(
                $this->visibilityFilter === 'visible',
                fn ($query) => $query->where('is_hidden', false)
            )
            ->when(
                $this->visibilityFilter === 'hidden',
                fn ($query) => $query->where('is_hidden', true)
            )
            ->when(trim($this->search) !== '', function ($query) {
                $search = '%' . trim($this->search) . '%';

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', $search)
                        ->orWhereHas('parent', function ($parentQuery) use ($search) {
                            $parentQuery->where('name', 'like', $search);
                        });
                });
            })
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function parentCategories()
    {
        return auth()->user()->categories()
            ->where('type', 'expense')
            ->whereNull('parent_id')
            ->where('is_hidden', false)
            ->when(
                $this->editingCategoryId,
                fn ($query) => $query->where('id', '!=', $this->editingCategoryId)
            )
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function summary(): array
    {
        $categories = auth()->user()->categories()
            ->where('type', $this->type);

        return [
            'total' => (clone $categories)->count(),
            'visible' => (clone $categories)->where('is_hidden', false)->count(),
            'hidden' => (clone $categories)->where('is_hidden', true)->count(),
        ];
    }

    public function openCreate(): void
    {
        $this->resetValidation();

        $this->editingCategoryId = null;
        $this->name = '';
        $this->parent_id = '';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $category = auth()->user()->categories()
            ->where('type', $this->type)
            ->findOrFail($id);

        $this->resetValidation();

        $this->editingCategoryId = $category->id;
        $this->name = $category->name;
        $this->parent_id = (string) ($category->parent_id ?? '');
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
            'type' => ['required', Rule::in(['income', 'expense'])],

            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')
                    ->where('user_id', auth()->id())
                    ->where('type', $this->type)
                    ->ignore($this->editingCategoryId),
            ],

            'parent_id' => [
                'nullable',
                'integer',
                Rule::requiredIf($this->type === 'expense' && $this->parent_id !== ''),
                Rule::exists('categories', 'id')
                    ->where('user_id', auth()->id())
                    ->where('type', 'expense')
                    ->whereNull('parent_id')
                    ->where('is_hidden', false),
            ],
        ]);

        $parentId = $this->type === 'expense' && $this->parent_id !== ''
            ? (int) $this->parent_id
            : null;

        if (
            $this->editingCategoryId &&
            $parentId === $this->editingCategoryId
        ) {
            $this->addError('parent_id', 'A category cannot be its own parent.');

            return;
        }

        if ($this->editingCategoryId) {
            $category = auth()->user()->categories()
                ->where('type', $this->type)
                ->findOrFail($this->editingCategoryId);

            if ($category->children()->exists() && $parentId !== null) {
                $this->addError(
                    'parent_id',
                    'A parent category with subcategories cannot become a subcategory.'
                );

                return;
            }

            $category->update([
                'name' => $validated['name'],
                'parent_id' => $parentId,
            ]);
        } else {
            auth()->user()->categories()->create([
                'name' => $validated['name'],
                'type' => $this->type,
                'parent_id' => $parentId,
                'is_default' => false,
                'default_key' => null,
                'is_hidden' => false,
            ]);
        }

        $this->showForm = false;
        $this->reset('name', 'parent_id', 'editingCategoryId');

        unset($this->categories, $this->parentCategories, $this->summary);

        session()->flash('success', 'Category saved successfully.');
    }

    public function toggleVisibility(int $id): void
    {
        $category = auth()->user()->categories()
            ->where('type', $this->type)
            ->findOrFail($id);

        $category->update([
            'is_hidden' => ! $category->is_hidden,
        ]);

        unset($this->categories, $this->summary);

        session()->flash(
            'success',
            $category->is_hidden
                ? 'Category hidden successfully.'
                : 'Category restored successfully.'
        );
    }
};
?>

<div class="mx-auto max-w-6xl space-y-6">

    {{-- Page heading --}}
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <flux:heading size="xl">Categories</flux:heading>
            <flux:subheading>
                Organise your income and expenses to keep your financial records clear.
            </flux:subheading>
        </div>

        <flux:button wire:click="openCreate" variant="primary" icon="plus">
            Add category
        </flux:button>
    </div>

    {{-- Flash message --}}
    @if (session()->has('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    {{-- Summary --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text color="green" size="sm">Total categories</flux:text>
            <flux:heading size="xl" class="mt-2">
                {{ $this->summary['total'] }}
            </flux:heading>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text color="green" size="sm">Visible</flux:text>
            <flux:heading size="xl" class="mt-2">
                {{ $this->summary['visible'] }}
            </flux:heading>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text color="green" size="sm">Hidden</flux:text>
            <flux:heading size="xl" class="mt-2">
                {{ $this->summary['hidden'] }}
            </flux:heading>
        </div>
    </div>

    {{-- Type tabs --}}
    <div class="flex gap-2">
        <flux:button
            wire:click="$set('type', 'expense')"
            variant="{{ $type === 'expense' ? 'primary' : 'ghost' }}"
        >
            Expenses
        </flux:button>

        <flux:button
            wire:click="$set('type', 'income')"
            variant="{{ $type === 'income' ? 'primary' : 'ghost' }}"
        >
            Income
        </flux:button>
    </div>

    {{-- Create / edit form --}}
    @if ($showForm)
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
            <div class="mb-5 flex items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">
                        {{ $editingCategoryId ? 'Edit category' : 'New ' . $type . ' category' }}
                    </flux:heading>

                    <flux:text color="green" size="sm">
                        Give your category a clear, recognisable name.
                    </flux:text>
                </div>

                <flux:button
                    wire:click="closeForm"
                    variant="ghost"
                    size="sm"
                    icon="x-mark"
                />
            </div>

            <form wire:submit="save" class="space-y-5">
                <div>
                    <flux:input
                        wire:model="name"
                        label="Category name"
                        placeholder="e.g. Software subscriptions"
                        required
                    />
                    @error('name')
                        <flux:text color="danger" size="sm">{{ $message }}</flux:text>
                    @enderror
                </div>

                @if ($type === 'expense')
                    <div>
                        <flux:select wire:model="parent_id" label="Parent category">
                            <flux:select.option value="">
                                None — create a parent category
                            </flux:select.option>

                            @foreach ($this->parentCategories as $parent)
                                <flux:select.option value="{{ $parent->id }}">
                                    {{ $parent->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:text color="green" size="sm" class="mt-1">
                            Choose a parent to create a subcategory, or leave it empty to create a parent category.
                        </flux:text>

                        @error('parent_id')
                            <flux:text color="danger" size="sm">{{ $message }}</flux:text>
                        @enderror
                    </div>
                @endif

                <div class="flex flex-wrap justify-end gap-2">
                    <flux:button type="button" wire:click="closeForm" variant="ghost">
                        Cancel
                    </flux:button>

                    <flux:button type="submit" variant="primary">
                        {{ $editingCategoryId ? 'Save changes' : 'Create category' }}
                    </flux:button>
                </div>
            </form>
        </div>
    @endif

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search categories..."
                icon="magnifying-glass"
            />
        </div>

        <div class="sm:w-48">
            <flux:select wire:model.live="visibilityFilter">
                <flux:select.option value="visible">Visible categories</flux:select.option>
                <flux:select.option value="hidden">Hidden categories</flux:select.option>
                <flux:select.option value="all">All categories</flux:select.option>
            </flux:select>
        </div>
    </div>

    {{-- Category list --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
            <flux:heading size="lg">
                {{ $type === 'expense' ? 'Expense categories' : 'Income categories' }}
            </flux:heading>
        </div>

        @forelse ($this->categories as $category)
            <div
                wire:key="category-{{ $category->id }}"
                class="flex flex-col gap-3 border-b border-zinc-100 px-5 py-4 last:border-b-0 dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex min-w-0 items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $type === 'income' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' }}">
                        <flux:icon
                            name="{{ $type === 'income' ? 'arrow-trending-up' : 'arrow-trending-down' }}"
                            class="size-5"
                        />
                    </div>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:text class="font-medium">
                                {{ $category->name }}
                            </flux:text>

                            @if ($category->is_default)
                                <flux:badge size="sm" color="blue">Default</flux:badge>
                            @endif

                            @if ($category->is_hidden)
                                <flux:badge size="sm" color="zinc">Hidden</flux:badge>
                            @endif
                        </div>

                        <flux:text color="green" size="sm">
                            @if ($category->parent)
                                {{ $category->parent->name }} · Subcategory
                            @else
                                Parent category
                            @endif

                            · {{ $category->transactions_count }}
                            {{ \Illuminate\Support\Str::plural('transaction', $category->transactions_count) }}
                        </flux:text>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:shrink-0">
                    <flux:button
                        wire:click="edit({{ $category->id }})"
                        variant="ghost"
                        size="sm"
                        icon="pencil"
                    >
                        Edit
                    </flux:button>

                    <flux:button
                        wire:click="toggleVisibility({{ $category->id }})"
                        wire:confirm="{{ $category->is_hidden ? 'Restore this category?' : 'Hide this category?' }}"
                        variant="ghost"
                        size="sm"
                    >
                        {{ $category->is_hidden ? 'Restore' : 'Hide' }}
                    </flux:button>
                </div>
            </div>
        @empty
            <div class="px-5 py-12 text-center">
                <flux:icon name="squares-2x2" class="mx-auto mb-3 size-8 text-zinc-400" />

                <flux:heading>No categories found</flux:heading>

                <flux:text color="green" class="mt-1">
                    @if ($search !== '')
                        Try another search term.
                    @else
                        Create a category to organise your {{ $type }} transactions.
                    @endif
                </flux:text>

                @if ($search === '')
                    <div class="mt-4">
                        <flux:button wire:click="openCreate" variant="primary" icon="plus">
                            Add category
                        </flux:button>
                    </div>
                @endif
            </div>
        @endforelse
    </div>
</div>
