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
    <link rel="preconnect" href='https://fonts.googleapis.com/css?family=Poppins' rel='stylesheet'>
    <script src="{{ asset('js/font-awesome.js') }}" defer crossorigin="anonymous"></script>
    {{--<link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">--}}
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="{{ asset('css/sweetalert2.min.css') }}">
    <script src="{{ asset('js/tailwind.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/jquery.dataTables.min.css') }}" media="print" onload="this.media='all'">
    <style>
        /* 1. Reset & General Layout */
        main {
            background-color: #ffffff !important;
        }

        .bg-gray-50 {
            background-color: #ffffff !important;
        }

        /* 2. Custom Progress Bar Refinement */
        #syncProgressWrapper {
            height: 4px;
        }

        #syncProgressBar {
            border-radius: 4px;
            font-size: 10px;
            line-height: 4px;
        }

        /* 3. DataTables Modernization */
        .dataTables_wrapper {
            padding: 1.5rem 0;
        }

        /* Search Input Styling */
        .dataTables_filter {
            margin-bottom: 1.5rem !important;
            margin-right: 1.5rem;
        }

        .dataTables_filter input {
            border: 1px solid #e5e7eb !important;
            border-radius: 8px !important;
            padding: 6px 12px !important;
            outline: none !important;
            transition: all 0.2s;
        }

        .dataTables_filter input:focus {
            border-color: #eee !important;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        /* Table & Header Refinement */
        table.dataTable.no-footer,
        table.NseSegmentTable {
            border: 1px solid #f3f4f6 !important;
            width: 96%;
            margin: 24px auto;
            border-radius: 5px;
        }

        .dataTable .even td {
            background: #eeeeee7a;
            min-height: 70px;
        }

        #activityTable thead th:nth-child(1) {
            width: 16px !important;
            padding-right: 10px !important;
        }

        #activityTable thead th {
            background-color: #eee;
            border-bottom: 2px solid #f3f4f6 !important;
            color: #000;
            letter-spacing: 0.025em;
            text-transform: uppercase;
            font-size: 0.7rem;
            padding: 1rem 1.5rem !important;
            padding-left: 10px !important;
        }

        #activityTable tbody tr {
            transition: background 0.2s;
        }

        #activityTable tbody td {
            /* border-bottom: 1px solid #f9fafb; */
            padding: 1rem 1.5rem !important;
            padding-left: 10px !important;
        }

        table.dataTable tbody th,
        table.dataTable tbody td {
            box-sizing: border-box !important;
        }

        /* Pagination Styling */
        .dataTables_paginate {
            padding-right: 1.5rem;
        }

        .paginate_button {
            border-radius: 6px !important;
            border: 1px solid #e5e7eb !important;
            background: white !important;
            margin-left: 4px !important;
        }

        .paginate_button.current {
            background: #4f46e5 !important;
            border-color: #4f46e5 !important;
        }

        #activityTable_paginate a.paginate_button.current {
            color: #fff !important;
        }

        #activityTable_paginate a.paginate_button:hover {
            background: #f9fafb !important;
            color: #000 !important;
        }

        /* 4. Custom Checkbox Refinement */
        .custom-checkbox {
            accent-color: #4f46e5;
            cursor: pointer;
        }

        /* 5. Action Buttons */
        .btn-action {
            border: 1px solid #e5e7eb;
            background: white;
            transition: all 0.2s;
        }

        .btn-action:hover {
            border-color: #4f46e5;
            color: #4f46e5;
            background: #f5f3ff;
        }

        .btn-bulk-action,
        .selectedCount {
            background: #4f46e5 !important;
        }

        .btn-bulk-action:hover,
        .selectedCount:hover {
            background: #4f46e5 !important;
        }

        .sorting:before,
        .sorting:after {
            position: absolute;
            left: 148px;
            opacity: .8;
        }

        .CreatedDate:before,
        .CreatedDate:after {
            left: 83px;
        }

        .LastUpdate:before,
        .LastUpdate:after {
            left: 116px;
        }

        table.dataTable thead>tr>th.sorting_asc:before,
        table.dataTable thead>tr>th.sorting_desc:after,
        table.dataTable thead>tr>td.sorting_asc:before,
        table.dataTable thead>tr>td.sorting_desc:after {
            opacity: 1 !important;
            color: #4f46e5 !important;
        }

        .sorting:hover:before,
        .sorting:hover:after {
            opacity: 1 !important;
        }

        .download_open {
            min-width: 125px !important;
            display: flex !important;
            justify-content: center;
            text-align: center;
            padding-left: unset !important;
            padding-right: unset !important;
        }

        .download_open:hover {
            color: #0a58ca !important;
        }

        .CreatedDate,
        .LastUpdate {
            width: 200px !important;
        }
    </style>
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

            0%,
            100% {
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

        /* Customizing DataTables to look better with your Tailwind theme */
        .dataTables_wrapper .dataTables_length select {
            padding-right: 2rem !important;
            border-radius: 6px;
        }

        .dataTables_wrapper .dataTables_filter input {
            border-radius: 0.5rem;
            padding: 0.4rem 1rem;
            border: 1px solid #e5e7eb;
        }

        table.dataTable thead th {
            border-bottom: 1px solid #e5e7eb !important;
        }

        div#activityTable_length {
            margin-left: 1.5rem;
        }

        .dataTables_info {
            margin-left: 1.5rem;
        }

        .paginate_button.disabled {
            cursor: not-allowed !important;
        }

        .action_col {
            width: 15% !important;
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
    <script src="{{ asset('js/lucide.min.js') }}"></script>
    <!-- Bootstrap Bundle Js -->
    {{--<script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>--}}
    <script src="{{ asset('js/sweetalert2@11.js') }}"></script>
    <script src="{{ asset('js/jquery.dataTables.min.js') }}" defer></script>
    <script>
        // 1. Initialize Icons
        lucide.createIcons();

        // 2. Clock
        function updateTime() {
            const timeElement = document.getElementById('current-time');

            if (timeElement) {
                const now = new Date();

                let hours = now.getHours();
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');

                const ampm = hours >= 12 ? 'PM' : 'AM';

                hours = hours % 12;
                hours = hours ? hours : 12; // 0 becomes 12

                hours = hours.toString().padStart(2, '0');

                timeElement.innerText = `${hours}:${minutes}:${seconds} ${ampm}`;
            }
        }

        setInterval(updateTime, 1000);
        updateTime(); // initial call
    </script>

    <script>
        $(document).ready(function() {
            const preloader = document.getElementById("preloader");

            let preloaderShownAt = null;
            const MIN_PRELOADER_TIME = 1000;

            function showPreloader() {
                if (!preloader) return;
                preloaderShownAt = Date.now();
                preloader.style.display = "flex";
                preloader.style.opacity = "1";
                document.body.classList.add("loading");
            }

            function hidePreloader() {
                if (!preloader) return;
                const elapsed = Date.now() - (preloaderShownAt || Date.now());
                const remaining = Math.max(0, MIN_PRELOADER_TIME - elapsed);

                setTimeout(() => {
                    preloader.style.opacity = "0";
                    setTimeout(() => {
                        preloader.style.display = "none";
                        document.body.classList.remove("loading");
                    }, 10);
                }, 100);
            }

            showPreloader();

            // In master blade — replace the DataTable init block with this:
            if ($('#activityTable').length) {
                let isInitialized = false;

                // ✅ Named global function — can be called from child blade after AJAX refresh
                window.initActivityTable = function() {
                    if ($.fn.DataTable.isDataTable('#activityTable')) {
                        $('#activityTable').DataTable().destroy();
                    }

                    isInitialized = false; // reset flag on each init

                    var table = $('#activityTable').DataTable({
                        "pageLength": 10,
                        "order": [
                            [1, "desc"],
                            [4, "desc"]
                        ],
                        "columnDefs": [{
                            "orderable": false,
                            "targets": [0, 5]
                        }],
                        "language": {
                            "search": "Search files:",
                            "emptyTable": "No activity found. Sync to fetch the latest files.",
                            paginate: {
                                previous: '<i class="fa fa-angle-left"></i>',
                                next: '<i class="fa fa-angle-right"></i>'
                            }
                        },
                        "preDrawCallback": function() {
                            if (!isInitialized) return;
                            showPreloader();
                        },
                        "drawCallback": function() {
                            hidePreloader();
                            if (typeof lucide !== 'undefined') lucide.createIcons();
                        },
                        "initComplete": function() {
                            isInitialized = true;
                            hidePreloader();
                            if (typeof lucide !== 'undefined') lucide.createIcons();

                            // ✅ Debounced search — reattach after every init
                            $('#activityTable_filter input').off('keyup search input');
                            let searchTimeout = null;
                            $('#activityTable_filter input').on('keyup input', function() {
                                const query = this.value.trim();
                                clearTimeout(searchTimeout);
                                if (query.length === 0) {
                                    table.search('').draw();
                                    return;
                                }
                                if (query.length < 3) return;
                                showPreloader();
                                searchTimeout = setTimeout(() => table.search(query).draw(), 10);
                            });
                        }
                    });
                };

                // Initial call on page load
                window.initActivityTable();

            } else {
                hidePreloader();
            }
        });
    </script>
    @yield('script')
</body>

</html>