<aside id="sidebar" class="bg-[#0f172a] text-slate-400 w-72 flex flex-col border-r border-slate-900 z-20 shadow-xl shrink-0">

    <div class="h-16 flex items-center justify-between px-4 border-b border-slate-800 bg-[#0f172a]">
        <div class="flex items-center gap-3 overflow-hidden whitespace-nowrap">
            <img src="{{ asset('assets/img/logo.ico') }}" alt="Logo" class="h-8 w-8 min-w-[2rem] rounded-lg object-contain bg-white/5">

            <div class="logo-text">
                <h1 class="text-slate-100 font-bold tracking-tight">Extranet Sync</h1>
            </div>
        </div>
        <button onclick="toggleSidebar()" class="text-slate-500 hover:text-white p-1 rounded-md hover:bg-slate-800 transition-colors">
            <i data-lucide="panel-left" class="w-5 h-5"></i>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-6 space-y-6">

        <div>
            <div class="nav-group-title px-3 mb-2 text-xs font-bold text-brand uppercase tracking-widest" style="color: var(--brand-color);">NSE Market</div>

            <div class="mb-1">
                <button onclick="toggleSubmenu('nse-member-sub', this)" class="nav-item w-full flex items-center justify-between px-3 py-2.5 rounded-lg hover:bg-slate-800 hover:text-slate-100 transition-all text-sm group text-slate-300">
                    <div class="flex items-center gap-3">
                        <i data-lucide="users" class="w-5 h-5 text-slate-500 group-hover:text-brand transition-colors"></i>
                        <span class="nav-text font-medium">Member Segment</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 chevron transition-transform duration-200"></i>
                </button>

                <div id="nse-member-sub" class="submenu pl-10 space-y-1">
                    <a href="{{ route('nse.segment.folder.today', ['segment' => 'cm', 'folder' => 'root']) }}" class="w-full text-left py-2 text-xs hover:text-white text-slate-500 block border-l border-slate-700 pl-4 hover:border-brand transition-colors">CM</a>
                    <a href="{{ route('nse.segment.folder.today', ['segment' => 'co', 'folder' => 'root']) }}" class="w-full text-left py-2 text-xs hover:text-white text-slate-500 block border-l border-slate-700 pl-4 hover:border-brand transition-colors">CO</a>
                    <a href="{{ route('nse.segment.folder.today', ['segment' => 'cd', 'folder' => 'root']) }}" class="w-full text-left py-2 text-xs hover:text-white text-slate-500 block border-l border-slate-700 pl-4 hover:border-brand transition-colors">CD</a>
                    <a href="{{ route('nse.segment.folder.today', ['segment' => 'fo', 'folder' => 'root']) }}" class="w-full text-left py-2 text-xs hover:text-white text-slate-500 block border-l border-slate-700 pl-4 hover:border-brand transition-colors">FO</a>
                </div>
            </div>

            <div>
                <button onclick="toggleSubmenu('nse-common-sub', this)" class="nav-item w-full flex items-center justify-between px-3 py-2.5 rounded-lg hover:bg-slate-800 hover:text-slate-100 transition-all text-sm group text-slate-300">
                    <div class="flex items-center gap-3">
                        <i data-lucide="layers" class="w-5 h-5 text-slate-500 group-hover:text-brand transition-colors"></i>
                        <span class="nav-text font-medium">Common Segment</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 chevron transition-transform duration-200"></i>
                </button>

                <div id="nse-common-sub" class="submenu pl-10 space-y-1">
                    <a href="{{ route('nse.common.segment.folder.today', ['segment' => 'cm', 'folder' => 'root']) }}" class="w-full text-left py-2 text-xs hover:text-white text-slate-500 block border-l border-slate-700 pl-4 hover:border-brand transition-colors">CM</a>
                    <a href="{{ route('nse.common.segment.folder.today', ['segment' => 'co', 'folder' => 'root']) }}" class="w-full text-left py-2 text-xs hover:text-white text-slate-500 block border-l border-slate-700 pl-4 hover:border-brand transition-colors">CO</a>
                    <a href="{{ route('nse.common.segment.folder.today', ['segment' => 'cd', 'folder' => 'root']) }}" class="w-full text-left py-2 text-xs hover:text-white text-slate-500 block border-l border-slate-700 pl-4 hover:border-brand transition-colors">CD</a>
                    <a href="{{ route('nse.common.segment.folder.today', ['segment' => 'fo', 'folder' => 'root']) }}" class="w-full text-left py-2 text-xs hover:text-white text-slate-500 block border-l border-slate-700 pl-4 hover:border-brand transition-colors">FO</a>
                </div>
            </div>
        </div>

        <div>
            <div class="nav-group-title px-3 mb-2 text-xs font-bold text-slate-500 uppercase tracking-widest">External</div>
            <ul class="space-y-1">
                <li>
                    <a href="#" class="nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-800 hover:text-slate-100 transition-all text-sm group">
                        <i data-lucide="bar-chart-2" class="w-5 h-5 text-slate-500 group-hover:text-brand transition-colors"></i>
                        <span class="nav-text font-medium">BSE Market</span>
                    </a>
                </li>
                <li>
                    <a href="#" class="nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-800 hover:text-slate-100 transition-all text-sm group">
                        <i data-lucide="globe" class="w-5 h-5 text-slate-500 group-hover:text-brand transition-colors"></i>
                        <span class="nav-text font-medium">MCX Market</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="p-4 border-t border-slate-800">
        <div class="flex items-center justify-between">
            <div class="text-sm">
                <p class="font-semibold text-slate-200">Welcome, {{ auth()->user()->name }}</p>
            </div>
            <form action="{{ route('admin.logout') }}" method="post">
                @csrf
                <button type="submit" class="flex items-center gap-2 text-sm font-semibold text-red-500 hover:text-red-400 transition-colors">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                </button>
            </form>
        </div>
    </div>
</aside>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    }

    function toggleSubmenu(id, btn) {
        const menu = document.getElementById(id);
        const icon = btn.querySelector('.chevron');

        document.querySelectorAll('.submenu').forEach(el => {
            if (el.id !== id) {
                el.classList.remove('open');
                const otherIcon = el.previousElementSibling.querySelector('.chevron');
                if (otherIcon) {
                    otherIcon.classList.remove('rotate-180');
                }
            }
        });

        if (menu.classList.contains('open')) {
            menu.classList.remove('open');
            icon.classList.remove('rotate-180');
        } else {
            menu.classList.add('open');
            icon.classList.add('rotate-180');
        }
    }
</script>
