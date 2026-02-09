@extends('layouts.user_type.auth')

@section('page_title', __('Dashboard'))


@section('style')
<style>
  :root {
    --icon-blue: #4a90e2;
    --hover-bg: #e8f0fe;
  }

  .explorer-container {
    font-family: 'Segoe UI', Roboto, sans-serif;
    background: #ffffff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
  }

  /* Toolbar Styling */
  .explorer-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #eee;
    padding-bottom: 15px;
    margin-bottom: 20px;
  }

  .view-controls button {
    padding: 5px 10px;
    cursor: pointer;
    border: 1px solid #ccc;
    background: #f9f9f9;
    border-radius: 4px;
    margin-left: 5px;
  }

  .view-controls button:hover {
    background: #eee;
  }

  /* Base Folder Item */
  .folder-item {
    display: flex;
    transition: all 0.2s ease;
    border-radius: 4px;
    padding: 10px;
    cursor: pointer;
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
    color: #333;
    font-weight: 500;
  }

  .folder-meta {
    font-size: 12px;
    color: #888;
    display: none;
  }

  /* --- VIEW MODES --- */

  /* 1. Big Grid (Windows Style) */
  .view-grid-big {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 120px));
    gap: 20px;
  }

  .view-grid-big .folder-item {
    flex-direction: column;
    align-items: center;
    text-align: center;
  }

  .view-grid-big .folder-icon svg {
    width: 64px;
    height: 64px;
    margin-bottom: 8px;
  }

  /* 2. Small Grid */
  .view-grid-small {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
  }

  .view-grid-small .folder-item {
    flex-direction: row;
    align-items: center;
  }

  .view-grid-small .folder-icon svg {
    width: 32px;
    height: 32px;
    margin-right: 10px;
  }

  /* 3. List View */
  .view-list {
    display: flex;
    flex-direction: column;
    border: 1px solid #eee;
  }

  .view-list .folder-item {
    flex-direction: row;
    align-items: center;
    border-bottom: 1px solid #f5f5f5;
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
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0">File Manager</h1>
        </div><!-- /.col -->
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="#">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">File Manager</a></li>
          </ol>
        </div><!-- /.col -->
      </div><!-- /.row -->
    </div><!-- /.container-fluid -->
  </div>
  <!-- /.content-header -->

  <!-- Main content -->
  <div class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col">
          <div class="explorer-container">
            <div class="explorer-toolbar">
              <span class="view-title">Market Exchanges</span>
              <div class="view-controls">
                <button onclick="changeView('view-grid-big')" title="Big Icons">▦</button>
                <button onclick="changeView('view-grid-small')" title="Small Icons">☷</button>
                <button onclick="changeView('view-list')" title="List View">☰</button>
              </div>
            </div>

            <div id="folderCanvas" class="view-grid-big">

              
              <a href="{{ route('nse.index') }}"s>
                <div class="folder-item">
                  <div class="folder-icon">
                    <svg viewBox="0 0 24 24">
                      <path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z" />
                    </svg>
                  </div>
                    <div class="folder-info">
                      <span class="folder-name">NSE</a>
                      <span class="folder-meta">24 Files</span>
                    </div>
                </div>
              </a>

              
              <a href="#" class="folder-name">
                <div class="folder-item">
                  <div class="folder-icon">
                    <svg viewBox="0 0 24 24">
                      <path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z" />
                    </svg>
                  </div>
                    <div class="folder-info">BSE</span>
                      <span class="folder-meta">18 Files</span>
                    </div>
                </div>
              </a>
            </div>
          </div>
        </div>
      </div>
      <!-- /.row -->
    </div><!-- /.container-fluid -->
  </div>
  <!-- /.content -->
</div>
<!-- /.content-wrapper -->

@endsection

@section('script')

<script>
  function changeView(viewClass) {
    const canvas = document.getElementById('folderCanvas');
    if (canvas) {
      canvas.classList.remove('view-grid-big', 'view-grid-small', 'view-list');
      canvas.classList.add(viewClass);

      // Optional: Save preference to localStorage so it stays after refresh
      localStorage.setItem('folder-view-pref', viewClass);
    }
  }

  // Optional: Load the saved preference on page load
  document.addEventListener('DOMContentLoaded', () => {
    const savedView = localStorage.getItem('folder-view-pref');
    if (savedView) changeView(savedView);
  });
</script>
@endsection