@extends('layouts.admin')

@section('title', 'Historique des Avancements')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- En-tête --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Historique des Avancements</h1>
                    <p class="mt-1 text-sm text-gray-600">Consultez et exportez les avancements de grade des distributeurs</p>
                </div>
                <a href="{{ route('admin.avancements.statistics') }}"
                   class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Statistiques
                </a>
            </div>
        </div>

        {{-- Filtres --}}
        <div class="bg-white rounded-lg shadow-sm mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Filtres de recherche</h3>
            </div>
            <div class="p-6">
                <form method="GET" action="{{ route('admin.avancements.index') }}" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label for="period" class="block text-sm font-medium text-gray-700">Période</label>
                        <select name="period" id="period"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                onchange="this.form.submit()">
                            @foreach($periods as $p)
                                <option value="{{ $p }}" {{ $period == $p ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::parse($p . '-01')->locale('fr')->isoFormat('MMMM YYYY') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="grade" class="block text-sm font-medium text-gray-700">Grade</label>
                        <select name="grade" id="grade"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            <option value="">Tous les grades</option>
                            @for($i = 1; $i <= 11; $i++)
                                <option value="{{ $i }}" {{ request('grade') == $i ? 'selected' : '' }}>
                                    {{ $i }} étoile{{ $i > 1 ? 's' : '' }}
                                </option>
                            @endfor
                        </select>
                    </div>

                    <div>
                        <label for="type_calcul" class="block text-sm font-medium text-gray-700">Type de calcul</label>
                        <select name="type_calcul" id="type_calcul"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            <option value="">Tous</option>
                            <option value="normal" {{ request('type_calcul') == 'normal' ? 'selected' : '' }}>Normal</option>
                            <option value="validated_only" {{ request('type_calcul') == 'validated_only' ? 'selected' : '' }}>Validés uniquement</option>
                        </select>
                    </div>

                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700">Rechercher</label>
                        <input type="text" name="search" id="search"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                               placeholder="Matricule ou nom..."
                               value="{{ request('search') }}">
                    </div>

                    <div class="flex items-end space-x-2">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            Filtrer
                        </button>
                        <a href="{{ route('admin.avancements.index') }}"
                           class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 focus:bg-gray-400 active:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Réinitialiser
                        </a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Statistiques --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            {{-- Total Avancements --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Avancements</dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900">{{ number_format($stats['total']) }}</div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-3">
                    <div class="text-sm">
                        <span class="font-medium text-primary-600 hover:text-primary-900">Période {{ $period }}</span>
                    </div>
                </div>
            </div>

            {{-- Promotions --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-12 w-12 rounded-md bg-green-500 text-white">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Promotions</dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900">{{ number_format($stats['promotions']) }}</div>
                                    @if($stats['total'] > 0)
                                        <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                            {{ round($stats['promotions'] / $stats['total'] * 100, 1) }}%
                                        </div>
                                    @endif
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Rétrogradations --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-12 w-12 rounded-md bg-red-500 text-white">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Rétrogradations</dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900">{{ number_format($stats['retrogradations']) }}</div>
                                    @if($stats['total'] > 0)
                                        <div class="ml-2 flex items-baseline text-sm font-semibold text-red-600">
                                            {{ round($stats['retrogradations'] / $stats['total'] * 100, 1) }}%
                                        </div>
                                    @endif
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-12 w-12 rounded-md bg-primary-500 text-white">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Exporter</dt>
                                <dd class="mt-1">
                                    <div class="flex space-x-2">
                                        <a href="{{ route('admin.avancements.export', ['period' => $period, 'format' => 'csv']) }}"
                                           class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                            CSV
                                        </a>
                                        <a href="{{ route('admin.avancements.export', ['period' => $period, 'format' => 'pdf']) }}"
                                           class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                            PDF
                                        </a>
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tableau des avancements --}}
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Liste des Avancements</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'distributeur_id', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc']) }}"
                                   class="group inline-flex">
                                    Matricule
                                    @if(request('sort') == 'distributeur_id')
                                        <span class="ml-2 flex-none rounded {{ request('direction') == 'asc' ? 'bg-gray-200' : 'bg-gray-200' }} text-gray-900 group-hover:bg-gray-300">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                @if(request('direction') == 'asc')
                                                    <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                                @else
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                @endif
                                            </svg>
                                        </span>
                                    @else
                                        <span class="invisible ml-2 flex-none rounded text-gray-400 group-hover:visible group-focus:visible">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    @endif
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Distributeur
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'ancien_grade', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc']) }}"
                                   class="group inline-flex">
                                    Ancien Grade
                                    @if(request('sort') == 'ancien_grade')
                                        <span class="ml-2 flex-none rounded {{ request('direction') == 'asc' ? 'bg-gray-200' : 'bg-gray-200' }} text-gray-900 group-hover:bg-gray-300">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                @if(request('direction') == 'asc')
                                                    <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                                @else
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                @endif
                                            </svg>
                                        </span>
                                    @endif
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'nouveau_grade', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc']) }}"
                                   class="group inline-flex">
                                    Nouveau Grade
                                    @if(request('sort') == 'nouveau_grade')
                                        <span class="ml-2 flex-none rounded {{ request('direction') == 'asc' ? 'bg-gray-200' : 'bg-gray-200' }} text-gray-900 group-hover:bg-gray-300">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                @if(request('direction') == 'asc')
                                                    <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                                @else
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                @endif
                                            </svg>
                                        </span>
                                    @endif
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Évolution
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'date_avancement', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc']) }}"
                                   class="group inline-flex">
                                    Date
                                    @if(request('sort') == 'date_avancement')
                                        <span class="ml-2 flex-none rounded {{ request('direction') == 'asc' ? 'bg-gray-200' : 'bg-gray-200' }} text-gray-900 group-hover:bg-gray-300">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                @if(request('direction') == 'asc')
                                                    <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                                @else
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                @endif
                                            </svg>
                                        </span>
                                    @endif
                                </a>
                            </th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($avancements as $avancement)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ $avancement->distributeur->distributeur_id }}</code>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-primary-100 flex items-center justify-center">
                                                <span class="text-primary-700 font-medium text-sm">
                                                    {{ substr($avancement->distributeur->nom_distributeur, 0, 1) }}{{ substr($avancement->distributeur->pnom_distributeur, 0, 1) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $avancement->distributeur->nom_distributeur }} {{ $avancement->distributeur->pnom_distributeur }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                ID: {{ $avancement->distributeur->id }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ $avancement->ancien_grade }} ⭐
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800">
                                        {{ $avancement->nouveau_grade }} ⭐
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                    @php
                                        $evolution = $avancement->nouveau_grade - $avancement->ancien_grade;
                                    @endphp
                                    @if($evolution > 0)
                                        <span class="inline-flex items-center text-green-600">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                            </svg>
                                            +{{ $evolution }}
                                        </span>
                                    @elseif($evolution < 0)
                                        <span class="inline-flex items-center text-red-600">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 10.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-6-6a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l4.293-4.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                            {{ $evolution }}
                                        </span>
                                    @else
                                        <span class="text-gray-500">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $avancement->type_calcul == 'validated_only' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                        {{ $avancement->type_calcul == 'validated_only' ? 'Validés' : 'Normal' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <time datetime="{{ $avancement->date_avancement }}">
                                        {{ \Carbon\Carbon::parse($avancement->date_avancement)->format('d/m/Y H:i') }}
                                    </time>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ route('admin.avancements.show', $avancement->id) }}"
                                       class="text-primary-600 hover:text-primary-900">
                                        Détails
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">Aucun avancement trouvé</h3>
                                    <p class="mt-1 text-sm text-gray-500">Aucun avancement ne correspond aux critères de recherche.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($avancements->hasPages())
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    {{ $avancements->links() }}
                </div>
            @endif
        </div>

        {{-- Top distributeurs --}}
        @if(count($stats['top_distributeurs']) > 0)
        <div class="mt-6 bg-white shadow-sm rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Top Distributeurs (Grade ≥ 7)</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($stats['top_distributeurs'] as $top)
                        <div class="relative rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm hover:border-gray-400 focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-primary-500">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="h-10 w-10 rounded-full bg-yellow-100 flex items-center justify-center">
                                        <span class="text-yellow-700 font-medium text-sm">
                                            {{ $top->nouveau_grade }}⭐
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <a href="{{ route('admin.distributeurs.show', $top->distributeur->id) }}" class="focus:outline-none">
                                        <span class="absolute inset-0" aria-hidden="true"></span>
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $top->distributeur->nom_distributeur }} {{ $top->distributeur->pnom_distributeur }}
                                        </p>
                                        <p class="text-sm text-gray-500 truncate">
                                            {{ $top->distributeur->distributeur_id }}
                                        </p>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Amélioration de l'apparence des selects */
    select {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 0.5rem center;
        background-repeat: no-repeat;
        background-size: 1.5em 1.5em;
        padding-right: 2.5rem;
    }
</style>
@endpush
