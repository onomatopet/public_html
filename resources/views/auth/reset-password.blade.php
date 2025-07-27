{{-- resources/views/auth/reset-password.blade.php --}}
@extends('layouts.guest')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Logo et titre -->
        <div class="text-center">
            <img class="mx-auto h-24 w-auto rounded-lg shadow-lg"
                 src="{{ asset('assets/img/logo.jpg') }}"
                 alt="Eternal Congo Logo">
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                Réinitialiser votre mot de passe
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                Créez un nouveau mot de passe sécurisé
            </p>
        </div>

        <!-- Formulaire de réinitialisation -->
        <form class="mt-8 space-y-6" method="POST" action="{{ route('password.store') }}">
            @csrf

            <!-- Token -->
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="bg-white shadow-2xl rounded-lg px-8 py-10">
                <!-- Messages d'erreur -->
                @if ($errors->any())
                    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <ul class="list-disc list-inside text-sm text-red-600">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="space-y-6">
                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">
                            Adresse email
                        </label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                                </svg>
                            </div>
                            <input id="email"
                                   name="email"
                                   type="email"
                                   autocomplete="email"
                                   required
                                   value="{{ old('email', $request->email) }}"
                                   class="appearance-none block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150 ease-in-out sm:text-sm @error('email') border-red-300 @enderror"
                                   placeholder="votre@email.com">
                        </div>
                        @error('email')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Nouveau mot de passe -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Nouveau mot de passe
                        </label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <input id="password"
                                   name="password"
                                   type="password"
                                   autocomplete="new-password"
                                   required
                                   class="appearance-none block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150 ease-in-out sm:text-sm @error('password') border-red-300 @enderror"
                                   placeholder="••••••••"
                                   autofocus>
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-xs text-gray-500">Minimum 8 caractères</p>
                    </div>

                    <!-- Confirmation du mot de passe -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                            Confirmer le nouveau mot de passe
                        </label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <input id="password_confirmation"
                                   name="password_confirmation"
                                   type="password"
                                   autocomplete="new-password"
                                   required
                                   class="appearance-none block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150 ease-in-out sm:text-sm"
                                   placeholder="••••••••">
                        </div>
                    </div>

                    <!-- Conseils de sécurité -->
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-amber-800">
                                    Conseils pour un mot de passe sécurisé
                                </h3>
                                <div class="mt-2 text-xs text-amber-700">
                                    <ul class="list-disc list-inside space-y-1">
                                        <li>Utilisez au moins 8 caractères</li>
                                        <li>Mélangez lettres, chiffres et symboles</li>
                                        <li>Évitez les mots du dictionnaire</li>
                                        <li>N'utilisez pas d'informations personnelles</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bouton de réinitialisation -->
                    <div>
                        <button type="submit"
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition duration-150 ease-in-out hover:scale-[1.02]">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <svg class="h-5 w-5 text-blue-300 group-hover:text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            Réinitialiser le mot de passe
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Footer -->
        <div class="text-center">
            <p class="text-xs text-gray-500">
                © {{ date('Y') }} Eternal Congo. Tous droits réservés.
            </p>
        </div>
    </div>
</div>
@endsection
