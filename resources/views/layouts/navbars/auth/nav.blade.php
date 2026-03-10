<header class="bg-white py-2 px-4 border-b border-slate-200">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-gray-900">@yield('header-title')</h1>
    </div>
    <div class="flex items-center gap-4">
        <div class="text-right">
          <div class="text-xs text-gray-500">
            {{ now()->format('D, M d, Y') }}
          </div>
          <div id="current-time" class="text-lg font-semibold text-gray-700"></div>
          @yield('header-timer')
        </div>
        @yield('header-actions')
    </div>
  </div>
</header>

<div id="preloader">
    <div class="loader-content">
        <img src="{{ asset('assets/img/wave preloader-loading.gif') }}" alt="Loading...">
    </div>
</div>

<style>
  #preloader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6); /* Semi-transparent gray */
    z-index: 9999; /* Keeps it above everything else */
    display: flex;
    justify-content: center;
    align-items: center;
    transition: opacity 0.5s ease; /* Smooth fade out */
}

.loader-content img {
    width: 100px; /* Adjust size as needed */
    height: auto;
}

/* Optional: Prevent scrolling while loading */
body.loading {
    overflow: hidden;
}
</style>

<script>
  window.addEventListener("load", function () {
    const preloader = document.getElementById("preloader");
    
    // Add a slight delay if you want the user to actually see the GIF 
    // for a split second on fast connections
    setTimeout(() => {
        preloader.style.opacity = "0";
        
        // Remove from DOM after fade-out to prevent blocking clicks
        setTimeout(() => {
            preloader.style.display = "none";
            document.body.classList.remove("loading");
        }, 500); 
    }, 1000); 
});
</script>