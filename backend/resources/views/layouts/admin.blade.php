<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Next Gen Relocation CRM — Command Centre">
    <title>@yield('title', 'Dashboard') — Next Gen Relocation CRM</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&family=Jost:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="min-h-screen">
    <div class="min-h-screen lg:flex">
        
        <!-- Sidebar -->
        <aside class="sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-r border-sidebar-border bg-sidebar lg:flex">
            <div class="px-5 pb-6 pt-6">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div class="relative shrink-0 overflow-hidden rounded-xl border border-gold/30 bg-background/60 w-10 h-10 gold-ring flex items-center justify-center font-display text-gold font-bold text-lg">
                        NG
                    </div>
                    <div class="leading-tight">
                        <div class="font-display text-lg font-semibold tracking-wide gold-text">NEXT GEN</div>
                        <div class="text-[10px] uppercase tracking-[0.35em] text-muted-foreground">Relocation Ltd</div>
                    </div>
                </div>
            </div>
            
            <nav class="flex-1 space-y-1 px-3">
                @php
                    $nav = [
                        ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'M3 3h7v9H3zm11 0h7v5h-7zm0 9h7v9h-7zM3 16h7v5H3z'],
                        ['route' => 'admin.leads.index', 'label' => 'Leads', 'icon' => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm14 10v-2a4 4 0 0 0-3-3.87m-4-12a4 4 0 0 1 0 7.75'],
                        ['route' => 'admin.calendar.index', 'label' => 'Calendar', 'icon' => 'M19 4H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zM16 2v4M8 2v4M3 10h18'],
                        ['route' => 'admin.contacts.index', 'label' => 'Contacts', 'icon' => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
                        ['route' => 'admin.calls.index', 'label' => 'Calls & Transcripts', 'icon' => 'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z'],
                        ['route' => 'admin.finance.index', 'label' => 'Finance', 'icon' => 'M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
                        ['route' => 'admin.integrations.index', 'label' => 'Integrations', 'icon' => 'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
                        ['route' => 'admin.outlook.index', 'label' => 'Outlook', 'icon' => 'M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z'],
                        ['route' => 'admin.settings.index', 'label' => 'Settings', 'icon' => 'M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z']
                    ];
                @endphp

                @foreach($nav as $item)
                    @php
                        $active = request()->routeIs($item['route']) || (str_contains($item['route'], 'leads') && request()->routeIs('admin.leads.*')) || (str_contains($item['route'], 'contacts') && request()->routeIs('admin.contacts.*'));
                    @endphp
                    <a href="{{ route($item['route']) }}" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-all {{ $active ? 'bg-gradient-to-r from-gold/20 to-gold/5 text-foreground gold-ring font-medium' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-foreground' }}">
                        <svg class="h-[18px] w-[18px] shrink-0 {{ $active ? 'text-gold' : 'text-muted-foreground group-hover:text-gold' }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            @if($item['label'] === 'Dashboard')
                                <rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>
                            @elseif($item['label'] === 'Leads')
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
                            @elseif($item['label'] === 'Calendar')
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            @elseif($item['label'] === 'Contacts')
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            @elseif($item['label'] === 'Finance')
                                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            @elseif($item['label'] === 'Integrations')
                                <path d="m18 8 4 4-4 4M6 8l-4 4 4 4M2 12h20"/>
                            @elseif($item['label'] === 'Outlook')
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                            @elseif($item['label'] === 'Settings')
                                <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            @endif
                        </svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            
            <div class="mx-3 mb-4 rounded-xl border border-gold/15 bg-success/5 px-4 py-3">
                <div class="flex items-center gap-2 text-xs">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-success" />
                    <span class="text-muted-foreground">All systems operational</span>
                </div>
            </div>
            
            <a href="/crm" onclick="localStorage.clear();" class="mx-3 mb-4 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-destructive">
                <svg class="h-[18px] w-[18px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Sign out
            </a>
            
            <div class="mx-3 mb-5 px-3 py-2 text-xs text-muted-foreground flex items-center gap-2">
                <span class="h-1.5 w-1.5 rounded-full bg-gold/50"></span>
                v1.0 — Laravel Live
            </div>
        </aside>

        <!-- Main Content Panel -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Top bar -->
            <header class="sticky top-0 z-40 flex items-center gap-3 border-b border-border/60 bg-background/70 px-4 py-3 backdrop-blur-xl lg:px-8">
                <button class="lg:hidden text-foreground">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="relative hidden flex-1 max-w-md md:block">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input
                        placeholder="Search leads, jobs, contacts…"
                        class="w-full rounded-full border border-border bg-input/40 py-2 pl-9 pr-4 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-gold/50"
                    />
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <button class="relative rounded-full border border-border bg-card/60 p-2 transition-colors hover:border-gold/40">
                        <svg class="h-[18px] w-[18px] text-muted-foreground" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <span class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-gold text-[9px] font-bold text-primary-foreground">
                            6
                        </span>
                    </button>
                    <div class="flex items-center gap-3 rounded-full border border-border bg-card/60 py-1 pl-1 pr-4">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-gold to-gold-dim text-primary-foreground font-bold text-xs">
                            Z
                        </div>
                        <div class="hidden text-left leading-tight sm:block">
                            <div class="text-xs font-medium text-foreground">Zul · Admin</div>
                            <div class="text-[10px] text-muted-foreground">Slough HQ</div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <!-- Alerts / Flashes -->
                @if(session('success'))
                    <div class="mb-6 rounded-xl border border-success/30 bg-success/10 px-4 py-3 text-sm text-success flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-success"></span>
                        {{ session('success') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
