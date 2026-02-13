<header class="bg-white py-2 px-4 border-b border-slate-200">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">NSE Member Segment</h1>
    </div>
    <div class="flex items-center gap-4">
        <div class="text-right">
          <div class="text-xs text-gray-500">
            {{ now()->format('D, M d, Y') }}
          </div>
          <div id="current-time" class="text-lg font-semibold text-gray-700"></div>
        </div>
        @yield('header-actions')
    </div>
  </div>
</header>