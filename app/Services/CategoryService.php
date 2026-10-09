<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function incomeCategories(): array
    {
        return [
            'income.sales_of_goods' => 'Sales of goods',
            'income.service_income' => 'Service income',
            'income.contract_project' => 'Contract / project income',
            'income.commission' => 'Commission',
            'income.interest_income' => 'Interest income',
            'income.other_income' => 'Other income',
        ];
    }

    public function expenseCategories(): array
    {
        return [
            'expense.premises_occupancy' => [
                'name' => 'Premises & occupancy',
                'children' => [
                    'rent' => 'Rent',
                    'utilities' => 'Utilities (electricity, water, gas)',
                    'premises_insurance' => 'Insurance (premises)',
                    'repairs_maintenance' => 'Repairs & maintenance',
                    'cleaning_security' => 'Cleaning & security',
                ],
            ],

            'expense.staff_labour' => [
                'name' => 'Staff & labour',
                'children' => [
                    'wages_salaries' => 'Wages & salaries',
                    'contractor_casual_labour' => 'Contractor / casual labour',
                    'staff_benefits_allowances' => 'Staff benefits & allowances',
                    'training' => 'Training',
                ],
            ],

            'expense.operations_supplies' => [
                'name' => 'Operations & supplies',
                'children' => [
                    'stock_purchases' => 'Stock / inventory purchases',
                    'raw_materials' => 'Raw materials',
                    'tools_equipment' => 'Tools & equipment',
                    'packaging_consumables' => 'Packaging & consumables',
                    'office_supplies' => 'Office supplies',
                ],
            ],

            'expense.vehicles_travel' => [
                'name' => 'Vehicles & travel',
                'children' => [
                    'fuel' => 'Fuel',
                    'vehicle_repairs_maintenance' => 'Vehicle repairs & maintenance',
                    'vehicle_insurance_licensing' => 'Vehicle insurance & licensing',
                    'parking_tolls' => 'Parking & tolls',
                    'business_travel_accommodation' => 'Business travel & accommodation',
                ],
            ],

            'expense.sales_marketing' => [
                'name' => 'Sales & marketing',
                'children' => [
                    'advertising' => 'Advertising',
                    'marketing_promotions' => 'Marketing & promotions',
                    'website_online_presence' => 'Website & online presence',
                    'marketing_materials_printing' => 'Marketing materials / printing',
                ],
            ],

            'expense.professional_financial' => [
                'name' => 'Professional & financial',
                'children' => [
                    'accounting_bookkeeping' => 'Accounting & bookkeeping fees',
                    'legal_fees' => 'Legal fees',
                    'bank_charges' => 'Bank charges',
                    'loan_interest' => 'Loan interest',
                    'licences_permits_registrations' => 'Licences, permits & registrations',
                ],
            ],

            'expense.communications_technology' => [
                'name' => 'Communications & technology',
                'children' => [
                    'phone_internet' => 'Phone & internet',
                    'software_subscriptions' => 'Software & subscriptions',
                    'it_support_equipment' => 'IT support & equipment',
                ],
            ],

            'expense.taxes_statutory' => [
                'name' => 'Taxes & statutory',
                'children' => [
                    'business_taxes' => 'Business taxes (as applicable)',
                    'government_fees_levies' => 'Government fees & levies',
                ],
            ],

            'expense.other' => [
                'name' => 'Other',
                'children' => [
                    'freight_delivery' => 'Freight & delivery',
                    'subscriptions_memberships' => 'Subscriptions & memberships',
                    'miscellaneous_sundry' => 'Miscellaneous / sundry',
                ],
            ],
        ];
    }

    public function createDefaultsFor(User $user): void
    {
        DB::transaction(function () use ($user) {
            foreach ($this->incomeCategories() as $key => $name) {
                Category::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'default_key' => $key,
                    ],
                    [
                        'name' => $name,
                        'type' => 'income',
                        'parent_id' => null,
                        'is_default' => true,
                        'is_hidden' => false,
                    ]
                );
            }

            foreach ($this->expenseCategories() as $groupKey => $group) {
                $parent = Category::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'default_key' => $groupKey,
                    ],
                    [
                        'name' => $group['name'],
                        'type' => 'expense',
                        'parent_id' => null,
                        'is_default' => true,
                        'is_hidden' => false,
                    ]
                );

                foreach ($group['children'] as $childKey => $name) {
                    Category::firstOrCreate(
                        [
                            'user_id' => $user->id,
                            'default_key' => "{$groupKey}.{$childKey}",
                        ],
                        [
                            'name' => $name,
                            'type' => 'expense',
                            'parent_id' => $parent->id,
                            'is_default' => true,
                            'is_hidden' => false,
                        ]
                    );
                }
            }
        });
    }

    public function createDefaultsForAllUsers(): void
    {
        User::query()
            ->select('id')
            ->orderBy('id')
            ->each(fn (User $user) => $this->createDefaultsFor($user));
    }
}
