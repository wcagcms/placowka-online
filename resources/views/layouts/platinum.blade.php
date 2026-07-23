<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Placówka Online — SaaS Platinum')</title>
    <meta name="description" content="@yield('meta_description', 'Monitoring infrastruktury placówki w czytelnym panelu SaaS Platinum.')">
    <link rel="stylesheet" href="{{ asset('assets/placowka-online-platinum/css/platinum.css') }}">
    @stack('head')
</head>
<body class="platinum-body">
    @yield('body')
    <script src="{{ asset('assets/placowka-online-platinum/js/platinum.js') }}" defer></script>
    <script src="{{ asset('panel/security-confirm.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
