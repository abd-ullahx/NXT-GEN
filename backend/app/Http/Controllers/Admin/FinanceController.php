<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function index()
    {
        $transactions = Transaction::orderBy('transaction_date', 'desc')->get();

        $totalIncome  = Transaction::where('type', 'income')->sum('amount');
        $totalExpense = Transaction::where('type', 'expense')->sum('amount');

        $revenueByMonth = [
            ['month' => 'Jan', 'revenue' => 38200, 'expenses' => 21400],
            ['month' => 'Feb', 'revenue' => 42100, 'expenses' => 22800],
            ['month' => 'Mar', 'revenue' => 51800, 'expenses' => 26300],
            ['month' => 'Apr', 'revenue' => 47600, 'expenses' => 24900],
            ['month' => 'May', 'revenue' => 58900, 'expenses' => 28100],
            ['month' => 'Jun', 'revenue' => 64300, 'expenses' => 29700],
        ];

        return view('admin.finance.index', compact('transactions', 'totalIncome', 'totalExpense', 'revenueByMonth'));
    }

    public function storeTransaction(Request $request)
    {
        $request->validate([
            'label' => 'required|string|max:255',
        ]);

        Transaction::create([
            'id'               => 'T-' . mt_rand(100000, 99999999),
            'transaction_date' => $request->input('transaction_date', now()->format('Y-m-d')),
            'label'            => $request->label,
            'category'         => $request->input('category', 'Job Income'),
            'type'             => $request->input('type', 'income'),
            'amount'           => $request->input('amount', 1000),
        ]);

        return redirect()->route('admin.finance.index')->with('success', 'Transaction created successfully.');
    }
}
