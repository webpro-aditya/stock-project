@extends('layouts.admin')

@section('auth')

    @include('layouts.navbars.auth.sidebar')
    <div class="flex-1 flex flex-col">
        @include('layouts.navbars.auth.nav')
        <main class="flex-1 overflow-y-auto">
            @yield('content')
        </main>
    </div>

@endsection