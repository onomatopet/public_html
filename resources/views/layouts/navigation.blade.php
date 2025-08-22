<nav class="flex flex-1 flex-col">
    <ul role="list" class="flex flex-1 flex-col gap-y-7">
        {{-- Section principale --}}
        <li>
            <ul role="list" class="-mx-2 space-y-1">
                {{-- Accueil --}}
                <li>
                    <a href="{{ route('admin.dashboard') }}"
                       class="{{ request()->routeIs('admin.dashboard') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                        Accueil
                    </a>
                </li>

                {{-- Distributeurs --}}
                <li>
                    <a href="{{ route('admin.distributeurs.index') }}"
                       class="{{ request()->routeIs('admin.distributeurs.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                        Distributeurs
                    </a>
                </li>

                {{-- Export Réseau --}}
                <li>
                    <a href="{{ route('admin.network.index') }}"
                       class="{{ request()->routeIs('admin.network.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" />
                        </svg>
                        Export Réseau
                    </a>
                </li>

                {{-- Achats --}}
                <li>
                    <a href="{{ route('admin.achats.index') }}"
                       class="{{ request()->routeIs('admin.achats.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                        </svg>
                        Achats
                    </a>
                </li>

                {{-- Produits --}}
                <li>
                    <a href="{{ route('admin.products.index') }}"
                       class="{{ request()->routeIs('admin.products.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                        </svg>
                        Produits
                    </a>
                </li>

                {{-- Bonus --}}
                <li>
                    <a href="{{ route('admin.bonuses.index') }}"
                       class="{{ request()->routeIs('admin.bonuses.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-5.25 0C4.243 3.089 1 6.79 1 11.25v.875c0 .621.504 1.125 1.125 1.125h18z" />
                        </svg>
                        Bonus
                    </a>
                </li>
            </ul>
        </li>

        {{-- Section Workflow & Périodes --}}
        <li>
            <div class="text-xs font-semibold leading-6 text-gray-400">WORKFLOW & PÉRIODES</div>
            <ul role="list" class="-mx-2 mt-2 space-y-1">
                {{-- Périodes --}}
                <li>
                    <a href="{{ route('admin.periods.index') }}"
                       class="{{ request()->routeIs('admin.periods.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        Périodes
                    </a>
                </li>

                {{-- Workflow --}}
                <li>
                    <a href="{{ route('admin.workflow.index') }}"
                       class="{{ request()->routeIs('admin.workflow.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                        </svg>
                        Workflow
                    </a>
                </li>

                {{-- Suppressions --}}
                <li>
                    <a href="{{ route('admin.deletions.index') }}"
                       class="{{ request()->routeIs('admin.deletions.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
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
                </li>

                {{-- Sauvegardes --}}
                @if(auth()->user()->hasPermission('view_period_backups'))
                <li>
                    <a href="{{ route('admin.periods.backups') }}"
                       class="{{ request()->routeIs('admin.periods.backups') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m6 4.125l2.25 2.25m0 0l2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                        </svg>
                        Sauvegardes
                    </a>
                </li>
                @endif
            </ul>
        </li>

        {{-- Section Administration --}}
        <li>
            <div class="text-xs font-semibold leading-6 text-gray-400">ADMINISTRATION</div>
            <ul role="list" class="-mx-2 mt-2 space-y-1">
                {{-- Rôles & Permissions --}}
                @if(auth()->user()->hasPermission('manage_roles'))
                <li>
                    <a href="{{ route('admin.roles.index') }}"
                       class="{{ request()->routeIs('admin.roles.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                        Rôles & Permissions
                    </a>
                </li>
                @endif

                {{-- Utilisateurs --}}
                @if(auth()->user()->hasPermission('view_users'))
                <li>
                    <a href="{{ route('admin.roles.users') }}"
                       class="{{ request()->is('admin/roles/users*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        Utilisateurs
                    </a>
                </li>
                @endif

                {{-- Rapports --}}
                <li>
                    <a href="{{ route('admin.reports.index') }}"
                       class="{{ request()->routeIs('admin.reports.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                        Rapports
                    </a>
                </li>

                {{-- Paramètres --}}
                @if(auth()->user()->hasPermission('manage_settings'))
                <li>
                    <a href="{{ route('admin.settings.index') }}"
                       class="{{ request()->routeIs('admin.settings.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Paramètres
                    </a>
                </li>
                @endif
            </ul>
        </li>

        {{-- Section Outils --}}
        <li>
            <div class="text-xs font-semibold leading-6 text-gray-400">OUTILS</div>
            <ul role="list" class="-mx-2 mt-2 space-y-1">
                {{-- Import/Export --}}
                <li>
                    <a href="{{ route('admin.import-export.index') }}"
                       class="{{ request()->routeIs('admin.import-export.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                        </svg>
                        Import/Export
                    </a>
                </li>

                {{-- Logs d'activité --}}
                @if(auth()->user()->hasPermission('view_logs'))
                <li>
                    <a href="{{ route('admin.logs.index') }}"
                       class="{{ request()->routeIs('admin.logs.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5M6 7.5h3v3H6v-3z" />
                        </svg>
                        Logs d'activité
                    </a>
                </li>
                @endif
            </ul>
        </li>

        {{-- Section Tableau de Bord (si nécessaire) --}}
        {{-- Vous pouvez décommenter cette section si vous avez ces routes
        <li>
            <div class="text-xs font-semibold leading-6 text-gray-400">TABLEAU DE BORD</div>
            <ul role="list" class="-mx-2 mt-2 space-y-1">
                <li>
                    <a href="{{ route('admin.dashboard.index') }}"
                       class="{{ request()->routeIs('admin.dashboard.index') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                        Vue d'ensemble
                    </a>
                </li>
            </ul>
        </li>
        --}}
    </ul>

    {{-- Section utilisateur en bas --}}
    <div class="mt-auto mb-0">
        <div class="border-t border-gray-200 pt-4">
            <div class="flex items-center gap-x-4 px-2">
                <img class="h-8 w-8 rounded-full bg-gray-50"
                     src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name) }}&color=7C3AED&background=EDE9FE"
                     alt="{{ auth()->user()->name }}">
                <div class="flex-1">
                    <div class="text-sm font-semibold text-gray-900">{{ auth()->user()->name }}</div>
                    <form method="POST" action="{{ route('logout') }}" class="mt-1">
                        @csrf
                        <button type="submit" class="text-xs text-gray-500 hover:text-gray-700">
                            Déconnexion
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</nav>