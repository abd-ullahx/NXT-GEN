<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    /**
     * Get complete unified financial accounting summary, quotations, invoices, ledger & P&L.
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Auto-sync won leads & job payments into transactions table only if sync flag requested or periodically
        if ($request->boolean('sync', false)) {
            $this->syncWonLeadsToFinance();
        }

        $monthFilter = $request->query('month'); // e.g. "2026-09" or "all"
        $rangeFilter = $request->query('range', '12m'); // "30d", "3m", "6m", "12m", "all"

        $serviceTypeFilter = $request->query('service_type', 'all');

        // 2. Fetch all transactions once
        $txQuery = Transaction::orderBy('transaction_date', 'desc');
        if ($serviceTypeFilter !== 'all') {
            $txQuery->where(function($q) use ($serviceTypeFilter) {
                $q->where('service_type', $serviceTypeFilter)
                  ->orWhere('service_type', 'general')
                  ->orWhereNull('service_type');
            });
        }
        $allTxs = $txQuery->get();

        // Attach parsed Y-m string to avoid repeating Carbon::parse inside loops
        $allTxs->transform(function ($t) {
            $t->ym = $t->transaction_date ? substr((string)$t->transaction_date, 0, 7) : null;
            return $t;
        });

        // Filter transactions based on month / range
        $filteredTxs = $allTxs->filter(function ($t) use ($monthFilter, $rangeFilter) {
            if (!$t->transaction_date) return true;
            $dateStr = (string) $t->transaction_date;

            if ($monthFilter && $monthFilter !== 'all') {
                return str_starts_with($dateStr, $monthFilter);
            }

            if ($rangeFilter === '30d') {
                return $dateStr >= now()->subDays(30)->format('Y-m-d');
            } elseif ($rangeFilter === '3m') {
                return $dateStr >= now()->subMonths(3)->startOfMonth()->format('Y-m-d');
            } elseif ($rangeFilter === '6m') {
                return $dateStr >= now()->subMonths(6)->startOfMonth()->format('Y-m-d');
            } elseif ($rangeFilter === '12m') {
                return $dateStr >= now()->subMonths(12)->startOfMonth()->format('Y-m-d');
            }

            return true;
        });

        $transactions = $filteredTxs->values()->map(fn($t) => [
            'id'       => $t->id,
            'date'     => (string) $t->transaction_date,
            'label'    => $t->label,
            'category' => $t->category ?: 'Job Income',
            'type'     => $t->type,
            'serviceType' => $t->service_type,
            'amount'   => (float) $t->amount,
        ]);

        // 3. Pre-group transactions by Y-m once for O(1) lookups
        $groupedByYm = $filteredTxs->groupBy('ym');

        $revenueByMonth = collect();

        if ($monthFilter && $monthFilter !== 'all' && preg_match('/^(\d{4})-(\d{2})$/', $monthFilter, $m)) {
            $dt = \Carbon\Carbon::createFromDate((int)$m[1], (int)$m[2], 1);
            $ym = $dt->format('Y-m');
            $groupTxs = $groupedByYm->get($ym, collect());
            $revenueByMonth->push([
                'month'    => $dt->format('M Y'),
                'revenue'  => (float) $groupTxs->where('type', 'income')->sum('amount'),
                'expenses' => (float) $groupTxs->where('type', 'expense')->sum('amount'),
            ]);
        } else {
            $monthsCount = 12;
            if ($rangeFilter === '30d') {
                $monthsCount = 1;
            } elseif ($rangeFilter === '3m') {
                $monthsCount = 3;
            } elseif ($rangeFilter === '6m') {
                $monthsCount = 6;
            } elseif ($rangeFilter === '12m') {
                $monthsCount = 12;
            } else {
                $monthsCount = 12;
            }

            for ($i = $monthsCount - 1; $i >= 0; $i--) {
                $dt = now()->subMonths($i);
                $ym = $dt->format('Y-m');
                $groupTxs = $groupedByYm->get($ym, collect());

                $revenueByMonth->push([
                    'month'    => $dt->format('M Y'),
                    'revenue'  => (float) $groupTxs->where('type', 'income')->sum('amount'),
                    'expenses' => (float) $groupTxs->where('type', 'expense')->sum('amount'),
                ]);
            }
        }

        // 4. Financial Core Summary (Based on filtered transactions or total)
        $totalIncome = (float) $filteredTxs->where('type', 'income')->sum('amount');
        $totalExpenses = (float) $filteredTxs->where('type', 'expense')->sum('amount');
        $netProfit = $totalIncome - $totalExpenses;
        $margin = $totalIncome > 0 ? round(($netProfit / $totalIncome) * 100, 1) : 0;

        // 5. Invoices & Accounts Receivable (A/R)
        $invQuery = Invoice::orderBy('created_at', 'desc');
        if ($serviceTypeFilter !== 'all') {
            $invQuery->whereHas('lead', function($q) use ($serviceTypeFilter) {
                $q->where('lead_type', $serviceTypeFilter);
            });
        }
        $rawInvoices = $invQuery->get();

        $invoices = $rawInvoices->map(fn($inv) => [
            'id'            => $inv->id,
            'leadId'        => $inv->lead_id,
            'quotationId'   => $inv->quotation_id,
            'invoiceNumber' => $inv->invoice_number,
            'clientName'    => $inv->client_name,
            'clientEmail'   => $inv->client_email,
            'serviceTitle'  => $inv->service_title,
            'status'        => $inv->status,
            'issueDate'     => $inv->issue_date?->format('Y-m-d'),
            'dueDate'       => $inv->due_date?->format('Y-m-d'),
            'items'         => $inv->items ?: [],
            'subtotal'      => (float) $inv->subtotal,
            'tax'           => (float) $inv->tax,
            'total'         => (float) $inv->total,
            'createdAt'     => $inv->created_at ? \Carbon\Carbon::parse($inv->created_at)->toIso8601String() : null,
        ]);

        $totalInvoiced = (float) $rawInvoices->sum('total');
        $totalPaidInvoices = (float) $rawInvoices->where('status', 'Paid')->sum('total');
        $accountsReceivable = (float) $rawInvoices->whereIn('status', ['Unpaid', 'Sent', 'Overdue', 'Draft'])->sum('total');
        $overdueInvoices = (float) $rawInvoices->filter(function($inv) {
            return $inv->status === 'Overdue' || (in_array($inv->status, ['Unpaid', 'Sent']) && $inv->due_date && $inv->due_date < now());
        })->sum('total');

        // 6. Quotations Pipeline
        $qQuery = Quotation::orderBy('created_at', 'desc');
        if ($serviceTypeFilter !== 'all') {
            $qQuery->whereHas('lead', function($q) use ($serviceTypeFilter) {
                $q->where('lead_type', $serviceTypeFilter);
            });
        }
        $rawQuotations = $qQuery->get();

        $quotations = $rawQuotations->map(fn($q) => [
            'id'            => $q->id,
            'leadId'        => $q->lead_id,
            'quoteType'     => $q->quote_type,
            'quoteNumber'   => $q->quote_number,
            'clientName'    => $q->client_name,
            'clientEmail'   => $q->client_email,
            'moveType'      => $q->move_type,
            'from'          => $q->from_location,
            'to'            => $q->to_location,
            'moveDate'      => $q->move_date?->format('Y-m-d'),
            'items'         => $q->items ?: [],
            'packages'      => $q->packages ?: [],
            'selectedPackage' => $q->selected_package,
            'subtotal'      => (float) $q->subtotal,
            'tax'           => (float) $q->tax,
            'total'         => (float) $q->total,
            'status'        => $q->status,
            'validUntil'    => $q->valid_until?->format('Y-m-d'),
            'sentAt'        => $q->sent_at ? \Carbon\Carbon::parse($q->sent_at)->toIso8601String() : null,
            'approvedAt'    => $q->approved_at ? \Carbon\Carbon::parse($q->approved_at)->toIso8601String() : null,
            'createdAt'     => $q->created_at ? \Carbon\Carbon::parse($q->created_at)->toIso8601String() : null,
        ]);

        $totalQuotedValue = (float) $rawQuotations->sum('total');
        $pendingQuotesTotal = (float) $rawQuotations->whereIn('status', ['draft', 'sent'])->sum('total');
        $approvedQuotesTotal = (float) $rawQuotations->where('status', 'approved')->sum('total');
        $approvedCount = $rawQuotations->where('status', 'approved')->count();
        $totalQuotesCount = $rawQuotations->count();
        $winRate = $totalQuotesCount > 0 ? round(($approvedCount / $totalQuotesCount) * 100, 1) : 0;

        // 7. Expense Breakdown by Category (Pure Eloquent calculation)
        $expenseBreakdown = $filteredTxs->where('type', 'expense')
            ->groupBy('category')
            ->map(function ($items, $cat) {
                return [
                    'name'  => $cat ?: 'General Operational',
                    'value' => (float) $items->sum('amount'),
                ];
            })
            ->values();

        // 8. Tax / VAT Calculation (Standard 20% UK VAT)
        $outputTax = round($totalIncome - ($totalIncome / 1.20), 2);
        $inputTax  = round($totalExpenses - ($totalExpenses / 1.20), 2);
        $netTaxLiability = max(0, $outputTax - $inputTax);

        return response()->json([
            'summary' => [
                'totalIncome'        => $totalIncome,
                'totalExpenses'      => $totalExpenses,
                'netProfit'          => $netProfit,
                'profitMargin'       => $margin,
                'accountsReceivable' => $accountsReceivable,
                'pendingQuotesTotal' => $pendingQuotesTotal,
                'outputTax'          => $outputTax,
                'inputTax'           => $inputTax,
                'netTaxLiability'    => $netTaxLiability,
            ],
            'invoicesSummary' => [
                'totalInvoiced'      => $totalInvoiced,
                'totalPaid'          => $totalPaidInvoices,
                'accountsReceivable' => $accountsReceivable,
                'overdue'            => $overdueInvoices,
                'totalCount'         => count($invoices),
            ],
            'quotationsSummary' => [
                'totalQuotedValue'   => $totalQuotedValue,
                'pendingValue'       => $pendingQuotesTotal,
                'approvedValue'      => $approvedQuotesTotal,
                'winRate'            => $winRate,
                'totalCount'         => count($quotations),
            ],
            'revenueByMonth'   => $revenueByMonth,
            'expenseBreakdown' => $expenseBreakdown,
            'transactions'     => $transactions,
            'invoices'         => $invoices,
            'quotations'       => $quotations,
        ]);
    }

    /**
     * Store a new custom income or expense transaction in the cash ledger.
     */
    public function storeTransaction(Request $request): JsonResponse
    {
        $request->validate([
            'label'        => 'required|string|max:255',
            'amount'       => 'required|numeric|min:0',
            'type'         => 'required|in:income,expense',
            'service_type' => 'nullable|string',
        ]);

        $newId = 'T-' . mt_rand(100000, 99999999);

        $tx = Transaction::create([
            'id'               => $newId,
            'transaction_date' => $request->input('date', now()->format('Y-m-d')),
            'label'            => trim($request->input('label')),
            'category'         => $request->input('category', $request->input('type') === 'income' ? 'Job Payment' : 'Operational Expense'),
            'type'             => $request->input('type', 'income'),
            'service_type'     => $request->input('service_type', 'general'),
            'amount'           => (float) $request->input('amount', 0),
        ]);

        return response()->json([
            'id'          => $tx->id,
            'date'        => $tx->transaction_date?->format('Y-m-d'),
            'label'       => $tx->label,
            'category'    => $tx->category,
            'type'        => $tx->type,
            'serviceType' => $tx->service_type,
            'amount'      => (float) $tx->amount,
        ], 201);
    }

    /**
     * Update an existing transaction in the general cash ledger.
     */
    public function updateTransaction(Request $request, string $id): JsonResponse
    {
        $tx = Transaction::find($id);
        if (!$tx) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        $request->validate([
            'label'  => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0',
            'type'   => 'sometimes|in:income,expense',
        ]);

        $data = array_filter([
            'label'            => $request->input('label'),
            'category'         => $request->input('category'),
            'type'             => $request->input('type'),
            'amount'           => $request->has('amount') ? (float) $request->input('amount') : null,
            'transaction_date' => $request->input('date'),
        ], fn ($v) => $v !== null);

        $tx->update($data);

        return response()->json([
            'message'     => 'Transaction updated successfully',
            'transaction' => [
                'id'       => $tx->id,
                'date'     => $tx->transaction_date?->format('Y-m-d'),
                'label'    => $tx->label,
                'category' => $tx->category,
                'type'     => $tx->type,
                'amount'   => (float) $tx->amount,
            ],
        ]);
    }

    /**
     * Delete a transaction from the cash ledger.
     */
    public function destroyTransaction(string $id): JsonResponse
    {
        $tx = Transaction::find($id);
        if (!$tx) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        $tx->delete();

        return response()->json(['message' => 'Transaction deleted successfully']);
    }

    /**
     * Helper to automatically convert won lead payments, paid invoices, and deposits into finance transactions.
     */
    private function syncWonLeadsToFinance(): void
    {
        // 1. Sync paid invoices with idempotent transaction IDs
        $paidInvoices = Invoice::with('lead')->where('status', 'Paid')->get();
        $leadIdsWithPaidInvoices = $paidInvoices->pluck('lead_id')->filter()->unique()->toArray();

        foreach ($paidInvoices as $inv) {
            $txId = 'TXN-INV-' . preg_replace('/[^A-Za-z0-9]/', '', $inv->id);
            $cat  = match ($inv->invoice_type) {
                'initial_deposit' => 'Deposit Payment',
                'plan_deposit'    => 'Deposit Payment',
                'final_balance'   => 'Final Job Balance',
                default           => 'Invoice Payment',
            };
            $method = match ($inv->payment_method) {
                'stripe'        => 'Stripe Card',
                'bank_transfer' => 'Bank Transfer (BACS)',
                'cash'          => 'Cash Received',
                default         => ucfirst((string)($inv->payment_method ?: 'Direct Payment')),
            };
            
            $serviceType = $inv->lead ? $inv->lead->lead_type : 'domestic';

            Transaction::updateOrCreate(
                ['id' => $txId],
                [
                    'transaction_date' => $inv->paid_at ? $inv->paid_at->format('Y-m-d') : ($inv->updated_at ? $inv->updated_at->format('Y-m-d') : now()->format('Y-m-d')),
                    'label'            => "Invoice #{$inv->invoice_number} ({$inv->service_title}) — {$inv->client_name} via {$method}",
                    'category'         => $cat,
                    'type'             => 'income',
                    'service_type'     => $serviceType,
                    'amount'           => (float) ($inv->paid_amount ?: $inv->total),
                ]
            );
        }

        // 2. Only sync won leads that DO NOT have any paid invoices
        $wonLeadsWithoutInvoices = Lead::where('status', 'won')
            ->whereNotIn('id', $leadIdsWithPaidInvoices)
            ->get();

        foreach ($wonLeadsWithoutInvoices as $lead) {
            $txId = 'JOB-INC-' . preg_replace('/[^A-Za-z0-9]/', '', $lead->id);
            $amount = (float) ($lead->total_deposit_paid ?: ($lead->est_value ?: 1200));

            Transaction::firstOrCreate(
                ['id' => $txId],
                [
                    'transaction_date' => $lead->quotation_approved_at ? date('Y-m-d', strtotime($lead->quotation_approved_at)) : now()->format('Y-m-d'),
                    'label'            => "Relocation Job Payment — {$lead->name} ({$lead->id})",
                    'category'         => 'Job Payment',
                    'type'             => 'income',
                    'service_type'     => $lead->lead_type ?: 'domestic',
                    'amount'           => $amount,
                ]
            );
        }
    }
}
