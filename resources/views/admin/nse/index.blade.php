@extends('layouts.user_type.auth')

@section('page_title', __('BSE Segments'))

@section('style')
<style>
    :root {
        --icon-blue: #4a90e2;
        --hover-bg: #e8f0fe;
        --active-btn: #d1e3fa;
    }

    .explorer-container {
        font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        background: #ffffff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 0; /* Changed to 0 to allow toolbar and list view to hit edges */
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }

    /* Toolbar Styling */
    .explorer-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8f9fa;
        padding: 12px 20px;
        border-bottom: 1px solid #dee2e6;
    }

    .view-title {
        font-weight: 600;
        color: #495057;
    }

    .view-controls button {
        padding: 4px 12px;
        cursor: pointer;
        border: 1px solid #ced4da;
        background: #ffffff;
        border-radius: 4px;
        margin-left: 4px;
        transition: all 0.2s;
        font-size: 1.1rem;
    }

    .view-controls button:hover {
        background: #e9ecef;
    }

    .view-controls button.active {
        background: var(--active-btn);
        border-color: var(--icon-blue);
        color: var(--icon-blue);
    }

    /* Base Folder Item */
    #folderCanvas {
        padding: 20px;
        /* min-height: 300px; */
    }

    .folder-item {
        display: flex;
        transition: background 0.15s ease;
        border-radius: 6px;
        padding: 10px;
        cursor: pointer;
        text-decoration: none !important;
    }

    .folder-item:hover {
        background-color: var(--hover-bg);
    }

    .folder-icon svg {
        fill: var(--icon-blue);
        display: block;
    }

    .folder-name {
        font-size: 14px;
        color: #212529;
        font-weight: 500;
        text-decoration: none;
    }

    .folder-meta {
        font-size: 12px;
        color: #6c757d;
        display: none;
    }

    /* --- VIEW MODES --- */

    /* 1. Big Grid */
    .view-grid-big {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(110px, 110px));
        gap: 15px;
    }

    .view-grid-big .folder-item {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .view-grid-big .folder-icon svg {
        width: 60px;
        height: 60px;
        margin-bottom: 8px;
    }

    /* 2. Small Grid */
    .view-grid-small {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 8px;
    }

    .view-grid-small .folder-item {
        flex-direction: row;
        align-items: center;
    }

    .view-grid-small .folder-icon svg {
        width: 28px;
        height: 28px;
        margin-right: 12px;
    }

    /* 3. List View */
    .view-list {
        display: flex !important;
        flex-direction: column;
        padding: 0 !important; /* List view looks better edge-to-edge */
    }

    .view-list .folder-item {
        flex-direction: row;
        align-items: center;
        padding: 8px 25px;
        border-bottom: 1px solid #f1f3f5;
        border-radius: 0;
    }

    .view-list .folder-icon svg {
        width: 20px;
        height: 20px;
        margin-right: 15px;
    }

    .view-list .folder-meta {
        display: block;
        margin-left: auto;
    }
</style>
@endsection

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <!-- <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">File Manager</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active"><a href="{{ route('dashboard.index') }}">File Manager</a></li>
                        <li class="breadcrumb-item active"><a href="#">Segments</a></li>
                    </ol>
                </div>
            </div> -->
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    {{--<div class="explorer-container">
                        <div class="explorer-toolbar">
                            <span class="view-title">NSE Segments</span>
                            <div class="view-controls" id="btnGroup">
                                <button onclick="changeView('view-grid-big', this)" title="Big Icons" class="active">▦</button>
                                <button onclick="changeView('view-grid-small', this)" title="Small Icons">☷</button>
                                <button onclick="changeView('view-list', this)" title="List View">☰</button>
                            </div>
                        </div>

                        <div id="folderCanvas" class="view-grid-big">
                            @php
                                $folders = [
                                    ['name' => 'CM', 'files' => '24 Files', 'url' => route('nse.segment', ['segment' => 'cm'])],
                                    ['name' => 'CD', 'files' => '18 Files', 'url' => route('nse.segment', ['segment' => 'cd'])],
                                    ['name' => 'FO', 'files' => '12 Files', 'url' => route('nse.segment', ['segment' => 'fo'])],
                                    ['name' => 'CO', 'files' => '9 Files', 'url' => route('nse.segment', ['segment' => 'co'])]
                                ];
                            @endphp

                            @foreach($folders as $folder)
                                <a href="{{ $folder['url'] }}" class="folder-item">
                                    <div class="folder-icon">
                                        <svg viewBox="0 0 24 24">
                                            <path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z" />
                                        </svg>
                                    </div>
                                    <div class="folder-info">
                                        <span class="folder-name">{{ $folder['name'] }}</span>
                                        <span class="folder-meta">{{ $folder['files'] }}</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>--}}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    /*
    // Using window scope to ensure availability if using Vite/Mix
    window.changeView = function(viewClass, btnElement) {
        const canvas = document.getElementById('folderCanvas');
        if (!canvas) return;

        // 1. Switch classes
        canvas.classList.remove('view-grid-big', 'view-grid-small', 'view-list');
        canvas.classList.add(viewClass);

        // 2. Handle Button Active States
        const buttons = document.querySelectorAll('.view-controls button');
        buttons.forEach(btn => btn.classList.remove('active'));
        if (btnElement) {
            btnElement.classList.add('active');
        }

        // 3. Save preference
        localStorage.setItem('folder-view-pref', viewClass);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const savedView = localStorage.getItem('folder-view-pref') || 'view-grid-big';
        
        // Match the button to the saved view to set initial active state
        const btnMap = {
            'view-grid-big': 0,
            'view-grid-small': 1,
            'view-list': 2
        };
        const buttons = document.querySelectorAll('.view-controls button');
        
        window.changeView(savedView, buttons[btnMap[savedView]]);
    });
    */
</script>
@endsection