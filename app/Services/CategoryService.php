<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    /**
     * The built-in income categories.
     */
    public function incomeCategories(): array
    {
        return [
            'Sales of goods',
            'Service income',
            'Contract / project income',
            'Commission',
            'Interest income',
            'Other income',
        ];
    }

    /**
     * Built-in expense groups and their subcategories.
     */
    public function expenseCategories(): array
    {
        return [
            'Premises & occupancy' => [
                'Rent',
                'Utilities (electricity, water, gas)',
                'Insurance (premises)',
                'Repairs & maintenance',
                'Cleaning & security',
            ],

            'Staff & labour' => [
                'Wages & salaries',
                'Contractor / casual labour',
                'Staff benefits & allowances',
                'Training',
            ],

            'Operations & supplies' => [
                'Stock / inventory purchases',
                'Raw materials',
                'Tools & equipment',
                'Packaging & consumables',
                'Office supplies',
            ],

            'Vehicles & travel' => [
                'Fuel',
                'Vehicle repairs & maintenance',
                'Vehicle insurance & licensing',
                'Parking & tolls',
                'Business travel & accommodation',
            ],

            'Sales & marketing' => [
                'Advertising',
                'Marketing & promotions',
                'Website & online presence',
                'Marketing materials / printing',
            ],

            'Professional & financial' => [
                'Accounting & bookkeeping fees',
                'Legal fees',
                'Bank charges',
                'Loan interest',
                'Licences, permits & registrations',
            ],

            'Communications & technology' => [
                'Phone & internet',
                'Software & subscriptions',
                'IT support & equipment',
            ],

            'Taxes & statutory' => [
                'Business taxes (as applicable)',
                'Government fees & levies',
            ],

            'Other' => [
                'Freight & delivery',
                'Subscriptions & memberships',
                'Miscellaneous / sundry',
            ],
        ];
    }

    /**
     * Create the built-in categories for a user.
     */
    public function createDefaultsFor(User $user): void
    {
        DB::transaction(function () use ($user) {
            foreach ($this->incomeCategories() as $name) {
                Category::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'name' => $name,
                        'type' => 'income',
                        'parent_id' => null,
                    ],
                    [
                        'is_default' => true,
                        'is_hidden' => false,
                    ]
                );
            }

            foreach ($this->expenseCategories() as $group => $items) {
                $parent = Category::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'name' => $group,
                        'type' => 'expense',
                        'parent_id' => null,
                    ],
                    [
                        'is_default' => true,
                        'is_hidden' => false,
                    ]
                );

                foreach ($items as $name) {
                    Category::firstOrCreate(
                        [
                            'user_id' => $user->id,
                            'name' => $name,
                            'type' => 'expense',
                            'parent_id' => $parent->id,
                        ],
                        [
                            'is_default' => true,
                            'is_hidden' => false,
                        ]
                    );
                }
            }
        });
    }

    /**
     * Seed defaults for every existing user.
     */
    public function createDefaultsForAllUsers(): void
    {
        User::query()
            ->select('id')
            ->orderBy('id')
            ->each(function (User $user) {
                $this->createDefaultsFor($user);
            });
    }
}
