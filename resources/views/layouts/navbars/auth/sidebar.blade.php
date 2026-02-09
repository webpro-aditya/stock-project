<style>
    .custom-sidebar-bg {
        background-color: #0f172a !important; /* Deep Navy Blue */
    }
    .brand-link {
        border-bottom: 1px solid #1e293b !important;
    }
    .nav-header {
        color: #64748b !important; /* Muted blue-grey text */
        font-size: 0.75rem !important;
        font-weight: 700 !important;
        letter-spacing: 0.05em;
        margin-top: 10px;
    }
    .nav-link {
        color: #94a3b8 !important; /* Light grey text */
    }
    .nav-link.active {
        background-color: #3b82f6 !important; /* Bright Blue Active State */
        color: #ffffff !important;
    }
    .nav-link:hover {
        color: #ffffff;
    }
    .nav-treeview .nav-link p {
        font-size: 0.9rem;
    }
</style>

<aside class="main-sidebar sidebar-dark-primary elevation-4 custom-sidebar-bg">
    <a href="{{ route('dashboard.index') }}" class="brand-link">
        <img src="{{ asset('assets/img/AdminLTELogo.png') }}" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-bold text-white">SyncConsole</span>
    </a>

    <div class="sidebar">
        
        <nav class="mt-3">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                
                <li class="nav-header">NSE MARKET</li>

                <li class="nav-item"> 
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-user-tie"></i> <p>
                            Member Segment
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('nse.segment.folder.today', ['segment' => 'cm', 'folder' => 'reports']) }}" class="nav-link">
                                <p class="pl-4">CM</p> </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('nse.segment.folder.today', ['segment' => 'co', 'folder' => 'reports']) }}" class="nav-link">
                                <p class="pl-4">CO</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('nse.segment.folder.today', ['segment' => 'cd', 'folder' => 'reports']) }}" class="nav-link">
                                <p class="pl-4">CD</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('nse.segment.folder.today', ['segment' => 'fo', 'folder' => 'reports']) }}" class="nav-link">
                                <p class="pl-4">FO</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-layer-group"></i> <p>
                            Common Segment
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('nse.segment.folder.today', ['segment' => 'cm', 'folder' => 'common']) }}" class="nav-link">
                                <p class="pl-4">CM</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('nse.segment.folder.today', ['segment' => 'co', 'folder' => 'common']) }}" class="nav-link">
                                <p class="pl-4">CO</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('nse.segment.folder.today', ['segment' => 'cd', 'folder' => 'common']) }}" class="nav-link">
                                <p class="pl-4">CD</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('nse.segment.folder.today', ['segment' => 'fo', 'folder' => 'common']) }}" class="nav-link">
                                <p class="pl-4">FO</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-header">EXTERNAL</li>

                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-chart-bar"></i> <p>BSE Market</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-globe"></i> <p>MCX Market</p>
                    </a>
                </li>

            </ul>
        </nav>
        </div>
    </aside>