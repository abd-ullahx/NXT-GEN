<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\JobEvent;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $stats = \Illuminate\Support\Facades\Cache::remember('api_dashboard_stats', 30, function () {
            $leads = Lead::select(['id', 'name', 'email', 'phone', 'source', 'status', 'move_type', 'lead_type', 'from_location', 'to_location', 'move_date', 'est_value', 'ai_score', 'priority'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($l) => [
                    'id'        => $l->id,
                    'name'      => $l->name,
                    'email'     => $l->email,
                    'phone'     => $l->phone,
                    'source'    => $l->source,
                    'status'    => $l->status,
                    'moveType'  => $l->move_type,
                    'leadType'  => $l->lead_type,
                    'from'      => $l->from_location,
                    'to'        => $l->to_location,
                    'moveDate'  => $l->move_date?->format('Y-m-d'),
                    'estValue'  => (float) $l->est_value,
                    'aiScore'   => $l->ai_score,
                    'priority'  => $l->priority,
                    'createdAgo' => 'recently',
                ]);

            $today = Carbon::today();
            $jobEvents = JobEvent::select(['id', 'title', 'type', 'event_date', 'event_time', 'driver', 'vehicle', 'location'])
                ->where('event_date', '>=', $today)
                ->orderBy('event_date', 'asc')
                ->get()
                ->map(fn($e) => [
                    'id'       => $e->id,
                    'title'    => $e->title,
                    'type'     => $e->type ?: 'Job',
                    'date'     => $e->event_date?->format('Y-m-d'),
                    'time'     => $e->event_time,
                    'driver'   => $e->driver,
                    'vehicle'  => $e->vehicle,
                    'location' => $e->location,
                ]);

            // Compute KPIs directly from MySQL database tables
            $pipelineValue = Lead::whereNotIn('status', ['lost'])->sum('est_value');
            $activeLeads = Lead::whereNotIn('status', ['won', 'lost'])->count();
            $wonRevenue = Lead::where('status', 'won')->sum('est_value');

            $kpis = [
                ['label' => 'Pipeline Value', 'value' => '£' . number_format($pipelineValue), 'delta' => '—', 'up' => true],
                ['label' => 'Active Leads', 'value' => (string) $activeLeads, 'delta' => '—', 'up' => true],
                ['label' => 'Jobs This Month', 'value' => (string) $jobEvents->count(), 'delta' => '—', 'up' => true],
                ['label' => 'Won Revenue (30d)', 'value' => '£' . number_format($wonRevenue), 'delta' => '—', 'up' => true],
            ];

            // Efficient DB-level aggregation instead of loading all records into memory
            $revenueByMonth = DB::table('transactions')
                ->selectRaw("DATE_FORMAT(transaction_date, '%b') as month_name")
                ->selectRaw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as revenue")
                ->selectRaw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expenses")
                ->whereNotNull('transaction_date')
                ->groupByRaw("DATE_FORMAT(transaction_date, '%b'), DATE_FORMAT(transaction_date, '%Y-%m')")
                ->orderByRaw("MIN(transaction_date)")
                ->get()
                ->map(fn ($row) => [
                    'month'    => $row->month_name,
                    'revenue'  => (float) $row->revenue,
                    'expenses' => (float) $row->expenses,
                ])
                ->toArray();

            return [
                'kpis'           => $kpis,
                'revenueByMonth' => $revenueByMonth,
                'leads'          => $leads,
                'jobEvents'      => $jobEvents,
                'database'       => 'MySQL',
            ];
        });

        return response()->json($stats);
    }

    private function syncWonLeadsToFinance(): void
    {
        $wonLeads = Lead::where('status', 'won')
            ->orWhereNotNull('quotation_approved_at')
            ->get();

        foreach ($wonLeads as $lead) {
            $txId = 'JOB-INC-' . preg_replace('/[^A-Za-z0-9]/', '', $lead->id);
            $amount = (float) ($lead->est_value ?: 2500);

            Transaction::firstOrCreate(
                ['id' => $txId],
                [
                    'transaction_date' => $lead->quotation_approved_at ? date('Y-m-d', strtotime($lead->quotation_approved_at)) : now()->format('Y-m-d'),
                    'label'            => "Relocation Job Payment — {$lead->name} ({$lead->id})",
                    'category'         => 'Job Payment',
                    'type'             => 'income',
                    'amount'           => $amount,
                ]
            );
        }
    }
}

