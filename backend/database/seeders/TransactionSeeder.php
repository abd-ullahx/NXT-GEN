<?php

namespace Database\Seeders;

use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $transactions = [
            // September 2026 Expenses
            [
                'id' => 'EXP-2026-09-01',
                'transaction_date' => '2026-09-05',
                'label' => 'Monthly Fleet Fuel & Diesel Expense',
                'category' => 'Fleet & Fuel',
                'type' => 'expense',
                'amount' => 1450.00,
            ],
            [
                'id' => 'EXP-2026-09-02',
                'transaction_date' => '2026-09-03',
                'label' => 'Driver & Mover Bi-weekly Payroll',
                'category' => 'Staff & Wages',
                'type' => 'expense',
                'amount' => 1930.00,
            ],

            // August 2026 Income & Expenses
            [
                'id' => 'INC-2026-08-01',
                'transaction_date' => '2026-08-15',
                'label' => 'Full Commercial Warehouse Relocation',
                'category' => 'Job Income',
                'type' => 'income',
                'amount' => 18500.00,
            ],
            [
                'id' => 'INC-2026-08-02',
                'transaction_date' => '2026-08-22',
                'label' => 'Luxury Residential Moving & Packing',
                'category' => 'Job Income',
                'type' => 'income',
                'amount' => 16495.00,
            ],
            [
                'id' => 'EXP-2026-08-01',
                'transaction_date' => '2026-08-10',
                'label' => 'Fleet Maintenance & Vehicle Servicing',
                'category' => 'Fleet & Fuel',
                'type' => 'expense',
                'amount' => 2800.00,
            ],
            [
                'id' => 'EXP-2026-08-02',
                'transaction_date' => '2026-08-18',
                'label' => 'Heavy Duty Packing Crates & Materials',
                'category' => 'Materials & Crates',
                'type' => 'expense',
                'amount' => 1420.00,
            ],
            [
                'id' => 'EXP-2026-08-03',
                'transaction_date' => '2026-08-28',
                'label' => 'Commercial Transit Liability Insurance',
                'category' => 'Insurance & Licenses',
                'type' => 'expense',
                'amount' => 2500.00,
            ],

            // July 2026 Income & Expenses
            [
                'id' => 'INC-2026-07-01',
                'transaction_date' => '2026-07-12',
                'label' => 'Corporate Office Move Deposit',
                'category' => 'Job Income',
                'type' => 'income',
                'amount' => 14200.00,
            ],
            [
                'id' => 'EXP-2026-07-01',
                'transaction_date' => '2026-07-18',
                'label' => 'Driver & Crew Monthly Wages',
                'category' => 'Staff & Wages',
                'type' => 'expense',
                'amount' => 3100.00,
            ],
            [
                'id' => 'EXP-2026-07-02',
                'transaction_date' => '2026-07-25',
                'label' => 'Fuel & Highway Toll Passes',
                'category' => 'Fleet & Fuel',
                'type' => 'expense',
                'amount' => 1650.00,
            ],

            // June 2026 Income & Expenses
            [
                'id' => 'INC-2026-06-01',
                'transaction_date' => '2026-06-19',
                'label' => 'Residential Villa Move',
                'category' => 'Job Income',
                'type' => 'income',
                'amount' => 11800.00,
            ],
            [
                'id' => 'EXP-2026-06-01',
                'transaction_date' => '2026-06-22',
                'label' => 'Bubble Wrap & Protective Boxes',
                'category' => 'Materials & Crates',
                'type' => 'expense',
                'amount' => 1250.00,
            ],

            // May 2026 Income & Expenses
            [
                'id' => 'INC-2026-05-01',
                'transaction_date' => '2026-05-14',
                'label' => 'Intercity Transport Service',
                'category' => 'Job Income',
                'type' => 'income',
                'amount' => 9600.00,
            ],
            [
                'id' => 'EXP-2026-05-01',
                'transaction_date' => '2026-05-20',
                'label' => 'Vehicle Oil & Tyre Replacements',
                'category' => 'Fleet & Fuel',
                'type' => 'expense',
                'amount' => 1800.00,
            ],
        ];

        foreach ($transactions as $tx) {
            Transaction::updateOrCreate(['id' => $tx['id']], $tx);
        }
    }
}
