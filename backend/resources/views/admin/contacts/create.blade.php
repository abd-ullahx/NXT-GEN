@extends('layouts.admin')

@section('title', 'New Contact')

@section('content')
<div class="space-y-6 max-w-3xl">
    <div>
        <div class="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-gold">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Directory
        </div>
        <h1 class="mt-1 font-display text-4xl font-semibold">New Contact</h1>
        <p class="text-sm text-muted-foreground">Add a new record to the contact directory.</p>
    </div>

    <div class="glass-card p-6 rounded-xl">
        <form action="{{ route('admin.contacts.store') }}" method="POST" class="space-y-4">
            @csrf
            
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Contact Name *</label>
                    <input name="name" required placeholder="John Smith" class="rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Email Address *</label>
                    <input type="email" name="email" required placeholder="john@example.com" class="rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Phone Number *</label>
                    <input name="phone" required placeholder="+44 7700 900000" class="rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Contact Type</label>
                    <select name="type" class="rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                        <option value="Customer">Customer</option>
                        <option value="Driver">Driver</option>
                        <option value="Supplier">Supplier</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Moves Completed</label>
                    <input type="number" name="moves" value="0" class="rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs text-muted-foreground">Lifetime Value LTV (£)</label>
                    <input type="number" name="lifetime_value" value="0" class="rounded-xl border border-border bg-input/40 px-3 py-2.5 text-sm outline-none focus:border-gold/50">
                </div>
            </div>
            
            <div class="pt-4 flex gap-3">
                <button type="submit" class="rounded-xl bg-gradient-to-r from-gold-bright to-gold-dim px-5 py-2.5 text-sm font-semibold text-primary-foreground hover:shadow-[var(--shadow-gold)]">
                    Create Contact
                </button>
                <a href="{{ route('admin.contacts.index') }}" class="rounded-xl border border-border bg-card/60 px-5 py-2.5 text-sm text-muted-foreground">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
