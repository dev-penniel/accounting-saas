<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $incomeCategories = [
            'Sales of goods',
            'Service income',
            'Contract / project income',
            'Commission',
            'Interest income',
            'Other income',
        ];

        $expenseCategories = [
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

        // Create defaults for every existing user.
        User::query()
            ->select('id')
            ->orderBy('id')
            ->each(function (User $user) use (
                $incomeCategories,
                $expenseCategories
            ) {
                DB::transaction(function () use (
                    $user,
                    $incomeCategories,
                    $expenseCategories
                ) {
                    // Income categories are flat by default.
                    foreach ($incomeCategories as $name) {
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

                    // Expense groups and their subcategories.
                    foreach ($expenseCategories as $groupName => $items) {
                        $parent = Category::firstOrCreate(
                            [
                                'user_id' => $user->id,
                                'name' => $groupName,
                                'type' => 'expense',
                                'parent_id' => null,
                            ],
                            [
                                'is_default' => true,
                                'is_hidden' => false,
                            ]
                        );

                        foreach ($items as $itemName) {
                            Category::firstOrCreate(
                                [
                                    'user_id' => $user->id,
                                    'name' => $itemName,
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
            });
    }
}
