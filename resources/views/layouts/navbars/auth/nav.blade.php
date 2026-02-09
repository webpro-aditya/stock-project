<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
    </ul>

    <ul class="navbar-nav ml-auto align-items-center">

        <li class="nav-item d-none d-sm-block">
            <div class="navbar-clock text-right mr-3 pr-3 border-right">
                <div id="navDate" style="font-size: 11px; font-weight: 700; color: #888; letter-spacing: 0.5px; text-transform: uppercase; line-height: 1;">
                    </div>
                <div id="navTime" style="font-size: 18px; font-weight: 700; color: #333; line-height: 1.2; font-variant-numeric: tabular-nums;">
                    </div>
            </div>
        </li>
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="fa-solid fa-square-caret-down" style="font-size: 20px;"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right">
                <form action="{{ route('admin.logout') }}" method="post">
                    @csrf
                    <button type="submit" class="dropdown-item">
                        <i class="fas fa-sign-out-alt mr-2 text-muted"></i> LogOut
                    </button>
                </form>
            </div>
        </li>

    </ul>
</nav>
<script>
    function updateNavbarClock() {
        const now = new Date();
        
        // 1. Format Time (HH : MM : SS)
        let hours = now.getHours();
        let minutes = now.getMinutes();
        let seconds = now.getSeconds();
        
        // Add leading zeros
        hours = hours < 10 ? '0' + hours : hours;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        
        document.getElementById('navTime').innerHTML = `${hours} <span style="color:#ccc">:</span> ${minutes} <span style="color:#ccc">:</span> ${seconds}`;

        // 2. Format Date (MON, FEB 9, 2026)
        const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
        const dateString = now.toLocaleDateString('en-US', options); // e.g., "Mon, Feb 9, 2026"
        
        document.getElementById('navDate').innerText = dateString;
    }

    // Start immediately and update every second
    setInterval(updateNavbarClock, 1000);
    updateNavbarClock();
</script>