{{-- resources/views/layouts/partials/admin-sidebar-content.blade.php --}}

<div class="flex h-16 shrink-0 items-center">
    <h1 class="text-xl font-bold text-primary-600">{{ config('app.name') }}</h1>
</div>

<nav class="flex flex-1 flex-col">
    <ul role="list" class="flex flex-1 flex-col gap-y-7">
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
                @if(auth()->user()->hasPermission('view_distributeurs') || true)
                <li>
                    <a href="{{ route('admin.network.index') }}"
                       class="{{ request()->routeIs('admin.network.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" />
                        </svg>
                        Export Réseau
                    </a>
                </li>
                @endif

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

        {{-- Section Tableau de Bord --}}
        <li>
            <div class="text-xs font-semibold leading-6 text-gray-400">Tableau de Bord</div>
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
                <li>
                    <a href="{{ route('admin.dashboard.performance') }}"
                       class="{{ request()->routeIs('admin.dashboard.performance') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                        </svg>
                        Performance
                    </a>
                </li>
            </ul>
        </li>

        {{-- Section Gestion MLM --}}
        <li>
            <div class="text-xs font-semibold leading-6 text-gray-400">Gestion MLM</div>
            <ul role="list" class="-mx-2 mt-2 space-y-1">
                <li>
                    <a href="{{ route('admin.periods.index') }}"
                       class="{{ request()->routeIs('admin.periods.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15zm0 2.25h.008v.008H16.5v-.008z" />
                        </svg>
                        Périodes
                        @php
                            $currentPeriod = \App\Models\SystemPeriod::getCurrentPeriod();
                        @endphp
                        @if($currentPeriod && $currentPeriod->status === 'validation')
                            <span class="ml-auto inline-block py-0.5 px-2 text-xs rounded-full bg-yellow-500 text-white">
                                À clôturer
                            </span>
                        @endif
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.workflow.index') }}"
                    class="{{ request()->routeIs('admin.workflow.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                        </svg>
                        Workflow
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.processes.index') }}"
                       class="{{ request()->routeIs('admin.processes.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Processus
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.snapshots.create') }}"
                       class="{{ request()->routeIs('admin.snapshots.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                        </svg>
                        Snapshots
                    </a>
                </li>
            </ul>
        </li>

        {{-- Section Gestion des données --}}
        <li>
            <div class="text-xs font-semibold leading-6 text-gray-400">Gestion des données</div>
            <ul role="list" class="-mx-2 mt-2 space-y-1">
                {{-- Suppressions avec sous-menu --}}
                @if(auth()->user()->hasPermission('view_deletion_requests') || true)
                <li class="relative">
                    <details class="group [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer {{ request()->routeIs('admin.deletion-requests.*') || request()->routeIs('admin.backups.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} rounded-md p-2 text-sm leading-6 font-semibold">
                            <div class="flex items-center gap-x-3">
                                <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                                <span>Suppressions</span>
                                @php
                                    $pendingCount = \App\Models\DeletionRequest::pending()->count();
                                @endphp
                                @if($pendingCount > 0)
                                    <span class="ml-2 inline-block py-0.5 px-2 text-xs rounded-full bg-red-500 text-white">
                                        {{ $pendingCount }}
                                    </span>
                                @endif
                            </div>
                            <svg class="h-5 w-5 shrink-0 transition duration-300 group-open:-rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </summary>

                        <ul class="mt-2 px-2 space-y-1">
                            <li>
                                <a href="{{ route('admin.deletion-requests.index') }}"
                                   class="{{ request()->routeIs('admin.deletion-requests.index') ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-50' }} block rounded-md py-2 pr-2 pl-9 text-sm leading-6">
                                    Demandes
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('admin.backups.index') }}"
                                   class="{{ request()->routeIs('admin.backups.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-50' }} block rounded-md py-2 pr-2 pl-9 text-sm leading-6">
                                    Backups
                                </a>
                            </li>
                        </ul>
                    </details>
                </li>
                @endif

                {{-- Demandes de modification --}}
                @if(auth()->user()->hasPermission('view_modification_requests') || true)
                <li>
                    <a href="{{ route('admin.modification-requests.index') }}"
                       class="{{ request()->routeIs('admin.modification-requests.*') ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Modifications
                        @php
                            $pendingModCount = \App\Models\ModificationRequest::pending()->count();
                        @endphp
                        @if($pendingModCount > 0)
                            <span class="ml-auto w-9 min-w-max whitespace-nowrap rounded-full bg-amber-500 px-2.5 py-0.5 text-center text-xs font-medium leading-5 text-white">
                                {{ $pendingModCount }}
                            </span>
                        @endif
                    </a>
                </li>
                @endif
            </ul>
        </li>
    </ul>
</nav>
