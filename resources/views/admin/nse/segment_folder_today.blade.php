@extends('layouts.user_type.auth')

@section('page_title', __('NSE Explorer - ' . Str::upper($segment)))

@section('style')
<style>
    :root {
        --bg-body: #f5f7fb;
        --text-main: #344767;
        --text-muted: #8392ab;
        --purple-brand: #5e72e4;
        --purple-icon: #5e72e4;
        --border-color: #e9ecef;
    }

    body {
        background-color: var(--bg-body);
        font-family: 'Open Sans', 'Segoe UI', sans-serif;
        overflow-y: hidden;
    }

    .content-wrapper {
        height: calc(100vh - 60px);
        display: flex;
        flex-direction: column;
        padding-bottom: 0;
    }

    .container-fluid {
        flex: 1;
        display: flex;
        flex-direction: column;
        padding-top: 15px !important;
        padding-bottom: 15px !important;
    }

    .page-header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        padding: 0 5px;
    }

    .page-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--text-main);
        margin: 0;
    }

    .header-right {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .btn-sync {
        background-color: var(--purple-brand);
        color: white;
        border: none;
        padding: 6px 16px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);
        transition: all 0.2s;
    }

    .btn-sync:hover {
        transform: translateY(-1px);
        box-shadow: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08);
        color: white;
    }

    .custom-breadcrumb {
        padding: 0 5px;
        margin-bottom: 15px;
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    .custom-breadcrumb a {
        color: var(--text-muted);
        text-decoration: none;
    }

    .custom-breadcrumb .active-item {
        color: var(--text-main);
        font-weight: 600;
    }

    .breadcrumb-separator {
        margin: 0 6px;
        color: #d1d1d1;
    }

    .white-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 15px 0 rgba(0, 0, 0, 0.05);
        padding: 15px 0 10px 20px;
        flex: 1;
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .card-heading {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-main);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .card-heading i {
        color: #fb8c00;
        font-size: 1.1rem;
    }

    .table-responsive {
        flex: 1;
        overflow-y: auto;
        padding-right: 20px;
    }

    .custom-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 8px;
    }

    .custom-table th {
        text-align: left;
        color: var(--text-muted);
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        padding: 0 10px 8px 10px;
        border-bottom: 1px solid var(--border-color);
        position: sticky;
        top: 0;
        background: white;
        z-index: 10;
    }

    .custom-table td {
        vertical-align: middle;
        padding: 6px 10px;
        color: var(--text-main);
        font-size: 0.85rem;
        background: white;
        border-bottom: 1px solid #f8f9fa;
    }

    .form-check-input {
        width: 16px;
        height: 16px;
        border-color: #d2d6da;
        cursor: pointer;
        margin-top: 2px;
    }

    .file-link {
        display: flex;
        align-items: center;
        text-decoration: none;
        color: var(--text-main);
        font-weight: 600;
        transition: color 0.2s;
    }

    .file-link:hover {
        color: var(--purple-brand);
    }

    .icon-box {
        margin-right: 12px;
        font-size: 1.1rem;
        color: var(--purple-icon);
    }

    .date-cell {
        color: var(--text-muted);
        font-weight: 500;
    }

    .btn-download {
        background: white;
        border: 1px solid #d2d6da;
        color: var(--text-main);
        font-size: 0.7rem;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-block;
    }

    .btn-download:hover {
        background: #f8f9fa;
        border-color: #b0b8c1;
        color: var(--text-main);
    }

    .archive-section {
        margin-top: 10px;
        text-align: center;
        color: #adb5bd;
        padding-right: 20px;
        flex-shrink: 0;
    }

    .archive-text {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
        color: #8392ab;
    }

    .btn-archive-arrow {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #f8f9fa;
        border: none;
        color: #8392ab;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.2s;
        font-size: 0.8rem;
    }

    .btn-archive-arrow:hover {
        background: #e9ecef;
        color: var(--text-main);
    }

    .pagination {
        margin-bottom: 0;
    }

    .page-link {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }
</style>
@endsection

@section('content')
<div class="content-wrapper">
    <div class="container-fluid">

        <div class="page-header-row">
            <h1 class="page-title">NSE Member Segment</h1>
            <div class="header-right">
                <button class="btn-sync">
                    <i class="fas fa-sync-alt"></i> SYNC NOW
                </button>
            </div>
        </div>

        <div class="custom-breadcrumb">
            <a href="#">NSE</a>
            <span class="breadcrumb-separator">/</span>
            <a href="#">Member Segment</a>
            <span class="breadcrumb-separator">/</span>
            <span class="active-item">{{ $folder ?? 'CAP' }}</span>
        </div>

        <div class="white-card">

            <div class="card-heading">
                <i class="fas fa-sun"></i> Today's Activity
            </div>

            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <th style="width: 45%;">Directory Name</th>
                            <th style="width: 20%;">Created</th>
                            <th style="width: 20%;">Last updated</th>
                            <th style="width: 15%; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contents as $item)
                        @php
                        $isFolder = ($item->type == 'folder');
                        $url = $isFolder
                        ? route('nse.segment', ['segment' => $segment, 'folder' => $item->name])
                        : '#';
                        @endphp
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input">
                            </td>
                            <td>
                                <a href="{{ $url }}" class="file-link">
                                    <div class="icon-box">
                                        <i class="{{ $isFolder ? 'far fa-folder' : 'far fa-file-alt' }}"></i>
                                    </div>
                                    {{ $item->name }}
                                </a>
                            </td>
                            <td class="date-cell">
                                {{ $item->created_at ? $item->created_at->format('d M H:i') : now()->format('d M H:i') }}
                            </td>
                            <td class="date-cell">
                                {{ $item->nse_modified_at ? $item->nse_modified_at->format('d M H:i') : now()->format('d M H:i') }}
                            </td>
                            <td style="text-align: right;">
                                @if(!$isFolder)
                                <button onclick="triggerDownload(this, {{ $item->id }})" class="btn-download">Download</button>
                                @else
                                <a href="{{ $url }}" class="btn-download">Open</a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <span class="text-muted">No activity found for today.</span>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="archive-section">
                <div class="archive-text">LOAD ARCHIVE HISTORY</div>
                <a href="{{ route('nse.segment.archives', ['segment' => $segment, 'folder' => $folder]) }}" class="btn-archive-arrow">
                    <i class="fas fa-arrow-down"></i>
                </a>
            </div>

        </div>

    </div>
</div>
@endsection

@section('script')
<script>
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    function triggerDownload(btn, id) {
        const originalText = btn.innerText;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Processing...';

        const url = "{{ route('nse.file.prepare', ['id' => ':id']) }}".replace(':id', id);

        fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    console.log(data);

                    Toast.fire({
                        icon: 'success',
                        title: 'Downloading...'
                    });

                    btn.innerHTML = '<i class="fas fa-check"></i> Done';

                    window.location.href = data.url;

                    setTimeout(() => {
                        btn.disabled = false;
                        btn.innerText = originalText;
                    }, 2000);
                } else {
                    throw new Error(data.message || 'Download failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);

                Toast.fire({
                    icon: 'error',
                    title: 'Download Failed',
                    text: error.message
                });

                btn.innerHTML = '<i class="fas fa-times"></i> Error';

                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerText = originalText;
                }, 3000);
            });
    }
</script>
@endsection