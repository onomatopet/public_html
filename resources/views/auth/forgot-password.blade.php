{{-- resources/views/auth/forgot-password.blade.php --}}
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
                Mot de passe oublié ?
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                Pas de panique, nous allons vous aider à le récupérer
            </p>
        </div>

        <!-- Formulaire de récupération -->
        <form class="mt-8 space-y-6" method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="bg-white shadow-2xl rounded-lg px-8 py-10">
                <!-- Messages de statut -->
                @if (session('status'))
                    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800">
                                    {{ session('status') }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

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
                    <!-- Description -->
                    <div class="text-sm text-gray-600 bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <p>
                            Entrez votre adresse email ci-dessous et nous vous enverrons un lien pour réinitialiser votre mot de passe.
                        </p>
                    </div>

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
                                   value="{{ old('email') }}"
                                   class="appearance-none block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150 ease-in-out sm:text-sm @error('email') border-red-300 @enderror"
                                   placeholder="votre@email.com"
                                   autofocus>
                        </div>
                        @error('email')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Bouton d'envoi -->
                    <div>
                        <button type="submit"
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition duration-150 ease-in-out hover:scale-[1.02]">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <svg class="h-5 w-5 text-blue-300 group-hover:text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </span>
                            Envoyer le lien de réinitialisation
                        </button>
                    </div>

                    <!-- Liens de navigation -->
                    <div class="flex items-center justify-between">
                        <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:text-blue-500 transition duration-150 ease-in-out flex items-center">
                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                            </svg>
                            Retour à la connexion
                        </a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="text-sm text-blue-600 hover:text-blue-500 transition duration-150 ease-in-out">
                                Créer un compte
                            </a>
                        @endif
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
