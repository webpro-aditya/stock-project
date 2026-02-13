<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- <link rel="icon" type="image/icon" href="{{ asset('assets/img/favicon.ico') }}"> -->
    <title>
        @hasSection('page_title')
            @yield('page_title') |
        @endif {{ __('Admin Dashboard') }}
    </title>
    <link href="{{ asset('css/styles.css?v1') }}" rel="stylesheet" />
    <link href='https://fonts.googleapis.com/css?family=Poppins' rel='stylesheet'>
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
         <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="{{ asset('css/all.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap');
    body {
        font-family: 'Inter', sans-serif;
    }
    .font-mono {
        font-family: 'JetBrains Mono', monospace;
    }
    :root {
        --brand-color: #4f46e5;
        --brand-hover: color-mix(in srgb, var(--brand-color), white 10%);
        --brand-light: color-mix(in srgb, var(--brand-color), white 90%);
    }
    .text-brand {
        color: var(--brand-color);
    }
    .bg-brand {
        background-color: var(--brand-color);
    }
    .bg-brand:hover {
        background-color: var(--brand-hover);
    }
    .bg-brand-light {
        background-color: var(--brand-light);
    }
    .border-brand {
        border-color: var(--brand-color);
    }
    aside {
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .submenu {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
    }
    .submenu.open {
        max-height: 500px;
        transition: max-height 0.5s ease-in;
    }
    aside.collapsed {
        width: 5rem;
    }
    aside.collapsed .logo-text,
    aside.collapsed .nav-group-title,
    aside.collapsed .nav-text,
    aside.collapsed .chevron {
        display: none;
    }
    aside.collapsed .nav-item {
        justify-content: center;
        padding-left: 0;
        padding-right: 0;
    }
    aside.collapsed .submenu {
        display: none;
    }
    #bulkActionBar {
        transition: transform 0.3s ease, opacity 0.3s ease;
    }
    .translate-y-full {
        transform: translateY(150%);
        opacity: 0;
    }
    .translate-y-0 {
        transform: translateY(0);
        opacity: 1;
    }
    .custom-checkbox {
        cursor: pointer;
        accent-color: var(--brand-color);
        width: 16px;
        height: 16px;
    }
    @keyframes bounce-y {
        0%, 100% {
            transform: translateY(-25%);
            animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
        }
        50% {
            transform: translateY(0);
            animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
        }
    }

    .animate-bounce-y-on-hover:hover {
        animation: bounce-y 1s infinite;
    }
</style>
@yield('style')
</head>

<body class="bg-gray-50 h-screen overflow-hidden">
    <div class="flex h-full">
        @auth
            @yield('auth')
        @endauth
        @guest
            @yield('guest')
        @endguest
    </div>


    <!-- jQuery -->
    <script src="{{ asset('js/jquery.min.js') }}"></script>
    <!-- Bootstrap Bundle Js -->
    <script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // 1. Initialize Icons
        lucide.createIcons();

        // 2. Clock
        function updateTime() {
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                const now = new Date();
                const hours = now.getHours().toString().padStart(2, '0');
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');
                timeElement.innerText = `${hours}:${minutes}:${seconds}`;
            }
        }
        setInterval(updateTime, 1000);
        updateTime(); // initial call
    </script>
    @yield('script')
</body>

</html>
