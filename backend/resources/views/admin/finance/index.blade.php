@extends('layouts.admin')

@section('title', 'Finance')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Financials
            </div>
            <h1 class="mt-1 font-display text-4xl font-semibold">Revenue & Expenses</h1>
            <p class="text-sm text-muted-foreground">Track income, costs and profitability.</p>
        </div>
        <button onclick="document.getElementById('addTxForm').classList.toggle('hidden')" class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-all hover:shadow-[var(--shadow-gold)]">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Transaction
        </button>
    </div>

    <!-- Hidden Tx form -->
    <div id="addTxForm" class="hidden glass-card p-5 rounded-xl">
        <h2 class="font-display text-lg font-semibold mb-4 text-gold">Add Transaction</h2>
        <form action="{{ route('admin.finance.storeTransaction') }}" method="POST">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Description *</label>
                    <input name="label" required placeholder="Deposit Payment" class="rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Type</label>
                    <select name="type" class="rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50">
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Category</label>
                    <select name="category" class="rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50">
                        <option value="Job Income">Job Income</option>
                        <option value="Fleet & Fuel">Fleet & Fuel</option>
                        <option value="Packing Materials">Packing Materials</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Amount (£) *</label>
                    <input type="number" name="amount" value="1000" step="0.01" class="rounded-xl border border-border bg-input/40 px-3 py-2 text-sm outline-none focus:border-gold/50">
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-4 py-2 text-sm font-semibold text-primary-foreground">Save Transaction</button>
                <button type="button" onclick="document.getElementById('addTxForm').classList.add('hidden')" class="rounded-xl border border-border bg-card/60 px-4 py-2 text-sm text-muted-foreground">Cancel</button>
            </div>
        </form>
    </div>

    <!-- KPI Summary Grid -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php
            $totalRevenue = collect($revenueByMonth)->sum('revenue');
            $totalExpenses = collect($revenueByMonth)->sum('expenses');
            $profit = $totalRevenue - $totalExpenses;
            $margin = number_format(($profit / $totalRevenue) * 100, 1);

            $cards = [
                ['label' => 'Total Revenue', 'value' => '£' . number_format($totalRevenue), 'delta' => '+18.6%', 'up' => true],
                ['label' => 'Total Expenses', 'value' => '£' . number_format($totalExpenses), 'delta' => '+6.2%', 'up' => false],
                ['label' => 'Net Profit', 'value' => '£' . number_format($profit), 'delta' => $margin . '% margin', 'up' => true],
                ['label' => 'Avg Job Value', 'value' => '£3,940', 'delta' => '+4.1%', 'up' => true]
            ];
        @endphp
        @foreach($cards as $c)
            <div class="glass-card hover p-5 rounded-xl">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wider text-muted-foreground">{{ $c['label'] }}</span>
                    <svg class="h-4 w-4 text-gold" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <div class="mt-2 font-display text-3xl font-semibold">{{ $c['value'] }}</div>
                <div class="mt-2 flex items-center gap-1 text-xs {{ $c['up'] ? 'text-success' : 'text-warning' }}">
                    @if($c['up'])
                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
                    @else
                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="7" x2="17" y2="17"/><polyline points="17 7 17 17 7 17"/></svg>
                    @endif
                    {{ $c['delta'] }}
                </div>
            </div>
        @endforeach
    </div>

    <!-- Charts Row -->
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="glass-card p-6 lg:col-span-2 rounded-xl">
            <h2 class="mb-4 font-display text-xl font-semibold">Monthly Performance</h2>
            <div class="h-64 flex items-end gap-6 pt-6">
                @php $maxVal = collect($revenueByMonth)->max('revenue'); @endphp
                @foreach($revenueByMonth as $month)
                    <div class="flex-1 flex flex-col items-center gap-2 h-full justify-end">
                        <div class="flex gap-2 items-end w-full justify-center h-48">
                            <div class="w-6 rounded bg-gold" style="height: {{ ($month['revenue'] / $maxVal) * 100 }}%;" title="£{{ number_format($month['revenue']) }}"></div>
                            <div class="w-6 rounded bg-muted" style="height: {{ ($month['expenses'] / $maxVal) * 100 }}%;" title="£{{ number_format($month['expenses']) }}"></div>
                        </div>
                        <div class="text-xs text-muted-foreground">{{ $month['month'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="glass-card p-6 rounded-xl">
            <h2 class="mb-2 font-display text-xl font-semibold">Expense Breakdown</h2>
            @php
                $expenses = [
                    ['name' => 'Fleet & Fuel', 'value' => 38, 'color' => 'bg-gold'],
                    ['name' => 'Wages', 'value' => 34, 'color' => 'bg-cyan-500'],
                    ['name' => 'Packing Materials', 'value' => 14, 'color' => 'bg-purple-500'],
                    ['name' => 'Marketing', 'value' => 9, 'color' => 'bg-amber-500'],
                    ['name' => 'Insurance', 'value' => 5, 'color' => 'bg-destructive']
                ];
            @endphp
            <div class="mt-4 space-y-3">
                @foreach($expenses as $e)
                    <div class="text-xs">
                        <div class="flex items-center justify-between mb-1">
                            <span class="flex items-center gap-2 text-muted-foreground">
                                <span class="h-2.5 w-2.5 rounded-full {{ $e['color'] }}"></span> {{ $e['name'] }}
                            </span>
                            <span class="font-medium text-foreground">{{ $e['value'] }}%</span>
                        </div>
                        <div class="w-full bg-muted/30 h-1.5 rounded-full overflow-hidden">
                            <div class="h-full {{ $e['color'] }}" style="width: {{ $e['value'] }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="glass-card overflow-hidden rounded-xl">
        <div class="border-b border-border/60 p-5">
            <h2 class="font-display text-xl font-semibold">Recent Transactions</h2>
        </div>
        <div class="divide-y divide-border/40">
            @foreach($transactions as $t)
                <div class="flex items-center gap-4 px-5 py-3.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full {{ $t->type === 'income' ? 'bg-success/15 text-success' : 'bg-destructive/15 text-destructive' }}">
                        @if($t->type === 'income')
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
                        @else
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="7" x2="17" y2="17"/><polyline points="17 7 17 17 7 17"/></svg>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium">{{ $t->label }}</div>
                        <div class="text-xs text-muted-foreground">{{ $t->category }} · {{ $t->transaction_date?->format('Y-m-d') }}</div>
                    </div>
                    <div class="font-medium {{ $t->type === 'income' ? 'text-success' : 'text-foreground' }}">
                        {{ $t->type === 'income' ? '+' : '−' }}£{{ number_format($t->amount) }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
