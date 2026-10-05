<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Contact;
use App\Models\JobEvent;
use App\Models\Transaction;

class DashboardController extends Controller
{
    public function index()
    {
        $leadsCount      = Lead::count();
        $contactsCount   = Contact::count();
        $jobsCount       = JobEvent::count();
        $recentLeads     = Lead::orderBy('created_at', 'desc')->take(5)->get();
        $upcomingEvents  = JobEvent::orderBy('event_date', 'asc')->take(5)->get();

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

        return view('admin.dashboard', compact(
            'leadsCount',
            'contactsCount',
            'jobsCount',
            'recentLeads',
            'upcomingEvents',
            'totalIncome',
            'totalExpense',
            'revenueByMonth'
        ));
    }
}
