<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'MLM System') }} - Bienvenue</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-bold text-blue-600">{{ config('app.name', 'MLM System') }}</h1>
                </div>
                <div class="flex items-center space-x-4">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="text-gray-700 hover:text-blue-600 transition">Tableau de bord</a>
                        @else
                            <a href="{{ route('login') }}" class="text-gray-700 hover:text-blue-600 transition">Connexion</a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">S'inscrire</a>
                            @endif
                        @endauth
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-blue-600 to-indigo-700 text-white pt-32 pb-20 px-4">
        <div class="absolute inset-0 bg-black opacity-10"></div>
        <div class="relative max-w-7xl mx-auto">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-4xl md:text-5xl font-bold mb-6">
                        Développez votre réseau,<br>
                        <span class="text-yellow-400">Multipliez vos revenus</span>
                    </h2>
                    <p class="text-xl mb-8 text-blue-100">
                        Rejoignez notre plateforme MLM innovante et construisez votre succès avec une équipe dynamique et des outils performants.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="{{ route('login') }}" class="bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition transform hover:scale-105 text-center">
                            Se connecter
                        </a>
                        <a href="#features" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-blue-600 transition transform hover:scale-105 text-center">
                            En savoir plus
                        </a>
                    </div>
                </div>
                <div class="hidden md:block">
                    <div class="float-animation">
                        <svg class="w-full h-auto" viewBox="0 0 500 400" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- Network illustration -->
                            <circle cx="250" cy="50" r="30" fill="#FCD34D"/>
                            <circle cx="150" cy="150" r="25" fill="#60A5FA"/>
                            <circle cx="250" cy="150" r="25" fill="#60A5FA"/>
                            <circle cx="350" cy="150" r="25" fill="#60A5FA"/>
                            <circle cx="100" cy="250" r="20" fill="#93C5FD"/>
                            <circle cx="200" cy="250" r="20" fill="#93C5FD"/>
                            <circle cx="300" cy="250" r="20" fill="#93C5FD"/>
                            <circle cx="400" cy="250" r="20" fill="#93C5FD"/>
                            <!-- Connection lines -->
                            <line x1="250" y1="80" x2="150" y2="125" stroke="#E5E7EB" stroke-width="2"/>
                            <line x1="250" y1="80" x2="250" y2="125" stroke="#E5E7EB" stroke-width="2"/>
                            <line x1="250" y1="80" x2="350" y2="125" stroke="#E5E7EB" stroke-width="2"/>
                            <line x1="150" y1="175" x2="100" y2="230" stroke="#E5E7EB" stroke-width="2"/>
                            <line x1="150" y1="175" x2="200" y2="230" stroke="#E5E7EB" stroke-width="2"/>
                            <line x1="350" y1="175" x2="300" y2="230" stroke="#E5E7EB" stroke-width="2"/>
                            <line x1="350" y1="175" x2="400" y2="230" stroke="#E5E7EB" stroke-width="2"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 px-4">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-12">
                <h3 class="text-3xl font-bold text-gray-900 mb-4">Pourquoi nous choisir ?</h3>
                <p class="text-xl text-gray-600">Des fonctionnalités pensées pour votre succès</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="bg-white p-8 rounded-xl shadow-lg hover:shadow-xl transition">
                    <div class="w-14 h-14 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <h4 class="text-xl font-semibold text-gray-900 mb-4">Gestion de réseau intuitive</h4>
                    <p class="text-gray-600">Visualisez et gérez facilement votre équipe avec des outils modernes et une interface claire.</p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white p-8 rounded-xl shadow-lg hover:shadow-xl transition">
                    <div class="w-14 h-14 bg-green-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h4 class="text-xl font-semibold text-gray-900 mb-4">Calcul automatique des bonus</h4>
                    <p class="text-gray-600">Système de commissionnement transparent avec calculs automatiques et rapports détaillés.</p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white p-8 rounded-xl shadow-lg hover:shadow-xl transition">
                    <div class="w-14 h-14 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <h4 class="text-xl font-semibold text-gray-900 mb-4">Tableaux de bord en temps réel</h4>
                    <p class="text-gray-600">Suivez vos performances et celles de votre équipe avec des statistiques actualisées en permanence.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-gray-100 py-20 px-4">
        <div class="max-w-4xl mx-auto text-center">
            <h3 class="text-3xl font-bold text-gray-900 mb-6">Prêt à démarrer votre succès ?</h3>
            <p class="text-xl text-gray-600 mb-8">Rejoignez des milliers de distributeurs qui ont transformé leur vie grâce à notre plateforme.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('login') }}" class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition transform hover:scale-105">
                    Accéder à mon espace
                </a>
                <a href="mailto:contact@example.com" class="bg-white text-gray-700 border-2 border-gray-300 px-8 py-3 rounded-lg font-semibold hover:border-gray-400 transition">
                    Nous contacter
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-12 px-4">
        <div class="max-w-7xl mx-auto">
            <div class="grid md:grid-cols-4 gap-8">
                <div>
                    <h5 class="text-white font-semibold mb-4">{{ config('app.name', 'MLM System') }}</h5>
                    <p class="text-sm">Votre partenaire pour un réseau de distribution performant.</p>
                </div>
                <div>
                    <h5 class="text-white font-semibold mb-4">Liens rapides</h5>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('login') }}" class="hover:text-white transition">Connexion</a></li>
                        <li><a href="#" class="hover:text-white transition">À propos</a></li>
                        <li><a href="#" class="hover:text-white transition">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h5 class="text-white font-semibold mb-4">Support</h5>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white transition">FAQ</a></li>
                        <li><a href="#" class="hover:text-white transition">Documentation</a></li>
                        <li><a href="#" class="hover:text-white transition">Assistance</a></li>
                    </ul>
                </div>
                <div>
                    <h5 class="text-white font-semibold mb-4">Légal</h5>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white transition">Conditions d'utilisation</a></li>
                        <li><a href="#" class="hover:text-white transition">Politique de confidentialité</a></li>
                        <li><a href="#" class="hover:text-white transition">Mentions légales</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-sm">
                <p>&copy; {{ date('Y') }} {{ config('app.name', 'MLM System') }}. Tous droits réservés.</p>
            </div>
        </div>
    </footer>
</body>
</html>
