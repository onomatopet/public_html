

<nav class="flex-1 px-2 py-4 space-y-8">
    {{-- Dashboard --}}
    <div>
        <a href="{{ route('admin.dashboard') }}"
           class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('admin.dashboard') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
            <svg class="mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Tableau de bord
        </a>
    </div>

    {{-- Gestion MLM --}}
    <div>
        <div class="text-xs font-semibold leading-6 text-gray-400">GESTION MLM</div>
        <div class="mt-2 space-y-1">
            {{-- Distributeurs --}}
            <a href="{{ route('admin.distributeurs.index') }}"
               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('admin.distributeurs.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Distributeurs
            </a>

            {{-- Achats --}}
            <a href="{{ route('admin.achats.index') }}"
               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('admin.achats.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                Achats
                @php
                    $pendingAchats = \App\Models\Achat::where('status', 'pending')->count();
                @endphp
                @if($pendingAchats > 0)
                    <span class="ml-auto inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        {{ $pendingAchats }}
                    </span>
                @endif
            </a>

            {{-- Produits --}}
            <a href="{{ route('admin.products.index') }}"
               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('admin.products.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                Produits
            </a>

            {{-- Bonus --}}
            <a href="{{ route('admin.bonuses.index') }}"
               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('admin.bonuses.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Bonus
            </a>

            {{-- Structure Réseau --}}
            <a href="{{ route('admin.network.index') }}"
               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('admin.network.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                Structure Réseau
            </a>
        </div>
    </div>

    {{-- Workflow & Périodes --}}
    <div>
        <div class="text-xs font-semibold leading-6 text-gray-400">WORKFLOW & PÉRIODES</div>
        <div class="mt-2 space-y-1">
            {{-- Périodes --}}
            <a href="{{ route('admin.periods.index') }}"
               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('admin.periods.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Périodes
                @php
                    $currentPeriod = \App\Models\SystemPeriod::getCurrentPeriod();
                @endphp
                @if($currentPeriod && $currentPeriod->status === 'validation')
                    <span class="ml-auto inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        À clôturer
                    </span>
                @endif
            </a>

            {{-- Workflow --}}
            <a href="{{ route('admin.workflow.index') }}"
               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('admin.workflow.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                Workflow
            </a>

            {{-- Suppressions --}}
            <a href="{{ route('admin.deletions.index') }}"
               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('admin.deletions.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Suppressions
                @php
                    $pendingDeletions = \App\Models\DeletionRequest::where('status', 'pending')->count();
                @endphp
                @if($pendingDeletions > 0)
                    <span class="ml-auto inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        {{ $pendingDeletions }}
                    </span>
                @endif
            </a>

            {{-- Sauvegardes --}}
            @if(auth()->user()->hasPermission('view_period_backups'))
            <a href="{{ route('admin.periods.backups') }}"
               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('admin.periods.backups') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V2"/>
                </svg>
                Sauvegardes
            </a>
            @endif
        </div>
    </div>

    {{-- Administration --}}
    <div>
        <div class="text-xs font-semibold leading-6 text-gray-400">ADMINISTRATION</div>
        <div class="mt-2 space-y-1">
            {{-- Utilisateurs & Rôles --}}
            @if(auth()->user()->hasPermission('manage_roles'))
            <a href="{{ route('admin.roles.index') }}"
               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('admin.roles.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Rôles & Permissions
            </a>
            @endif

            {{-- Utilisateurs --}}
            @if(auth()->user()->hasPermission('view_users'))
            <a href="{{ route('admin.roles.users') }}"
               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->is('admin/roles/users*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Utilisateurs
            </a>
            @endif

            {{-- Rapports --}}
            <a href="{{ route('admin.reports.index') }}"
               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('admin.reports.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Rapports
            </a>

            {{-- Paramètres --}}
            @if(auth()->user()->hasPermission('manage_settings'))
            <a href="{{ route('admin.settings.index') }}"
               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('admin.settings.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Paramètres
            </a>
            @endif
        </div>
    </div>

    {{-- Outils --}}
    <div>
        <div class="text-xs font-semibold leading-6 text-gray-400">OUTILS</div>
        <div class="mt-2 space-y-1">
            {{-- Import/Export --}}
            <a href="{{ route('admin.import-export.index') }}"
               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('admin.import-export.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                </svg>
                Import/Export
            </a>

            {{-- Logs d'activité --}}
            @if(auth()->user()->hasPermission('view_logs'))
            <a href="{{ route('admin.logs.index') }}"
               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('admin.logs.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                <svg class="mr-3 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Logs d'activité
            </a>
            @endif
        </div>
    </div>
</nav>

{{-- Bouton de déconnexion en bas --}}
<div class="flex-shrink-0 flex border-t border-gray-800 p-4">
    <div class="flex-shrink-0 w-full group block">
        <div class="flex items-center">
            <div>
                <img class="inline-block h-9 w-9 rounded-full"
                     src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name) }}&color=7F9CF5&background=EBF4FF"
                     alt="{{ auth()->user()->name }}">
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-white">
                    {{ auth()->user()->name }}
                </p>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-xs font-medium text-gray-400 hover:text-gray-300">
                        Déconnexion
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
