
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- Title -->
        <title>Alpha | Responsive Admin Dashboard Template</title>

        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
        <meta charset="UTF-8">
        <meta name="description" content="Responsive Admin Dashboard Template" />
        <meta name="keywords" content="admin,dashboard" />
        <meta name="author" content="Steelcoders" />

        <!-- Styles -->
        <link type="text/css" rel="stylesheet" href="{{ asset('public/assets/plugins/materialize/css/materialize.min.css') }}"/>
        <link href="http://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <link href="{{ asset('public/athemes/alpha/plugins/material-preloader/css/materialPreloader.min.css') }}" rel="stylesheet">


        <!-- Theme Styles -->
        <link href="{{ asset('public/themes/alpha/css/alpha.min.css')}}" rel="stylesheet" type="text/css"/>
        <link href="{{ asset('public/themes/alpha/custom.css')}}" rel="stylesheet" type="text/css"/>


        <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
        <script src="http://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
        <script src="http://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->

    </head>
    <body class="error-page page-coming-soon">

        <div class="loader-bg"></div>
        <div class="loader">
            <div class="preloader-wrapper big active">
                <div class="spinner-layer spinner-blue">
                    <div class="circle-clipper left">
                        <div class="circle"></div>
                    </div><div class="gap-patch">
                    <div class="circle"></div>
                    </div><div class="circle-clipper right">
                    <div class="circle"></div>
                    </div>
                </div>
                <div class="spinner-layer spinner-red">
                    <div class="circle-clipper left">
                        <div class="circle"></div>
                    </div><div class="gap-patch">
                    <div class="circle"></div>
                    </div><div class="circle-clipper right">
                    <div class="circle"></div>
                    </div>
                </div>
                <div class="spinner-layer spinner-yellow">
                    <div class="circle-clipper left">
                        <div class="circle"></div>
                    </div><div class="gap-patch">
                    <div class="circle"></div>
                    </div><div class="circle-clipper right">
                    <div class="circle"></div>
                    </div>
                </div>
                <div class="spinner-layer spinner-green">
                    <div class="circle-clipper left">
                        <div class="circle"></div>
                    </div><div class="gap-patch">
                    <div class="circle"></div>
                    </div><div class="circle-clipper right">
                    <div class="circle"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mn-content">
            <main class="mn-inner container">
                <div class="center">
                    <h1>
                        <span id="countdown"></span>
                    </h1>
                    <span class="text-white f-s-32">Eternal Congo, votre site bientôt disponible !</span>
                </div>
                <div><p>&nbsp;</p></div>
                <div class="center">
                     <div class="relative sm:flex sm:justify-center sm:items-center min-h-screen bg-dots-darker bg-center bg-gray-100 selection:bg-red-500 selection:text-white">
                        @if (Route::has('login'))
                            <div class="sm:fixed sm:top-0 sm:right-0 p-6 text-right z-10">
                                @auth
                                    <a href="{{ url('/dashboard') }}" class="font-semibold text-gray-600 hover:text-gray-900 focus:outline focus:outline-2 focus:rounded-sm focus:outline-red-500">Accéder à l'Application</a>
                                @else
                                    <a href="{{ route('login') }}" class="waves-effect waves-light btn white">Se Connecter</a>
                                    @if (Route::has('register'))
                                        <span></span>
                                    @endif
                                @endauth
                            </div>
                        @endif
                    </div>
                </div>
            </main>
        </div>

        <!-- Javascripts -->
        <script src="{{ asset('public/assets/plugins/jquery/jquery-2.2.0.min.js') }}"></script>
        <script src="{{ asset('public/assets/plugins/materialize/js/materialize.min.js') }}"></script>
        <script src="{{ asset('public/assets/plugins/material-preloader/js/materialPreloader.min.js') }}"></script>
        <script src="{{ asset('public/assets/plugins/jquery-blockui/jquery.blockui.js') }}"></script>
        <script src="{{ asset('public/assets/plugins/jquery.countdown/jquery.countdown.min.js') }}"></script>
        <script src="{{ asset('public/assets/js/alpha.min.js') }}"></script>
        <script src="{{ asset('public/assets/js/pages/coming-soon.js') }}"></script>

    </body>
</html
