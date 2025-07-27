{{-- resources/views/auth/verify-email.blade.php --}}
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
                Vérifiez votre email
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                Une dernière étape pour finaliser votre inscription
            </p>
        </div>

        <div class="bg-white shadow-2xl rounded-lg px-8 py-10">
            <!-- Message de statut -->
            @if (session('status') == 'verification-link-sent')
                <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">
                                Un nouveau lien de vérification a été envoyé à votre adresse email.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="space-y-6">
                <!-- Icône d'email animée -->
                <div class="flex justify-center">
                    <div class="relative">
                        <svg class="h-24 w-24 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <div class="absolute -top-1 -right-1">
                            <span class="flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-blue-500"></span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Message principal -->
                <div class="text-center">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">
                        Vérification en attente
                    </h3>
                    <p class="text-sm text-gray-600">
                        Nous avons envoyé un email de vérification à votre adresse.
                        Veuillez cliquer sur le lien dans l'email pour activer votre compte.
                    </p>
                </div>

                <!-- Informations supplémentaires -->
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-amber-800">
                                Vous n'avez pas reçu l'email ?
                            </h3>
                            <div class="mt-2 text-xs text-amber-700">
                                <ul class="list-disc list-inside space-y-1">
                                    <li>Vérifiez votre dossier spam</li>
                                    <li>Assurez-vous que l'adresse email est correcte</li>
                                    <li>Attendez quelques minutes</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="space-y-4">
                    <!-- Renvoyer l'email -->
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button type="submit"
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition duration-150 ease-in-out hover:scale-[1.02]">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <svg class="h-5 w-5 text-blue-300 group-hover:text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                            </span>
                            Renvoyer l'email de vérification
                        </button>
                    </form>

                    <!-- Se déconnecter -->
                    <form method="POST" action="{{ route('logout') }}" class="text-center">
                        @csrf
                        <button type="submit" class="text-sm text-gray-600 hover:text-gray-900 underline transition duration-150 ease-in-out">
                            Se déconnecter et réessayer plus tard
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center">
            <p class="text-xs text-gray-500">
                © {{ date('Y') }} Eternal Congo. Tous droits réservés.
            </p>
        </div>
    </div>
</div>
@endsection
