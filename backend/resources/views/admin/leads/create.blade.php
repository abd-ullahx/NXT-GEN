@extends('layouts.admin')

@section('title', 'New Lead')

@section('content')
<div class="space-y-6 max-w-3xl">
    <div>
        <div class="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            Pipeline
        </div>
        <h1 class="mt-1 font-display text-4xl font-semibold">New Lead</h1>
        <p class="text-sm text-muted-foreground">Add a new lead to the relocation pipeline.</p>
    </div>

    <div class="glass-card p-6 rounded-xl">
        <form action="{{ route('admin.leads.store') }}" method="POST" class="space-y-4">
            @csrf
            
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Customer Name *</label>
                    <input name="name" required placeholder="Eleanor Whitmore" class="rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Email Address *</label>
                    <input type="email" name="email" required placeholder="e.whitmore@gmail.com" class="rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Phone Number *</label>
                    <input name="phone" required placeholder="+44 7700 900812" class="rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Lead Source</label>
                    <select name="source" class="rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                        <option value="Website Form">Website Form</option>
                        <option value="CompareMyMove">CompareMyMove</option>
                        <option value="PinLocal">PinLocal</option>
                        <option value="reallymoving">reallymoving</option>
                        <option value="Facebook">Facebook</option>
                        <option value="Google Ads">Google Ads</option>
                        <option value="Referral">Referral</option>
                        <option value="Phone">Phone</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Move Type (e.g. 4-Bed House → Detached) *</label>
                    <input name="move_type" required placeholder="4-Bed House → Detached" class="rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">From Location *</label>
                    <input name="from_location" required placeholder="Slough" class="rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">To Location *</label>
                    <input name="to_location" required placeholder="Windsor" class="rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Move Date</label>
                    <input type="date" name="move_date" value="{{ now()->addWeeks(2)->format('Y-m-d') }}" class="rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Estimated Value (£)</label>
                    <input type="number" name="est_value" value="2500" class="rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Priority</label>
                    <select name="priority" class="rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                        <option value="Hot">Hot</option>
                        <option value="Warm">Warm</option>
                        <option value="Cold">Cold</option>
                    </select>
                </div>
            </div>
            
            <div class="pt-4 flex gap-3">
                <button type="submit" class="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2.5 text-sm font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)]">
                    Create Lead
                </button>
                <a href="{{ route('admin.leads.index') }}" class="rounded-xl border border-border bg-card/60 px-5 py-2.5 text-sm text-muted-foreground">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
