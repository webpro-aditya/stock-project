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
    }

    .container-fluid {
        flex: 1;
        display: flex;
        flex-direction: column;
        padding: 15px !important;
        overflow: hidden; /* Contains the scrollable card */
    }

    /* --- Header --- */
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
        display: flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11);
    }

    /* --- Main Card --- */
    .white-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 15px 0 rgba(0, 0, 0, 0.05);
        padding: 20px;
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden; /* Important for internal scroll */
    }

    .card-heading {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-main);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }

    /* --- Accordion Styling --- */
    .archive-list-container {
        flex: 1;
        overflow-y: auto; /* Scroll only this area */
        padding-right: 10px;
    }

    .archive-item {
        border-bottom: 1px solid #f0f2f5;
        margin-bottom: 10px;
    }

    .archive-header {
        display: flex;
        align-items: center;
        padding: 15px 10px;
        cursor: pointer;
        transition: background 0.2s;
        border-radius: 8px;
    }

    .archive-header:hover {
        background-color: #f8f9fa;
    }

    /* Date Badge (e.g., "08") */
    .date-badge {
        width: 40px;
        height: 40px;
        background-color: #f8f9fa;
        color: #344767;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        margin-right: 15px;
        border: 1px solid #e9ecef;
    }

    .archive-info {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .archive-date-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--text-main);
    }

    .archive-meta {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .accordion-arrow {
        color: #ced4da;
        transition: transform 0.3s;
    }

    /* Expanded State */
    .archive-header[aria-expanded="true"] .accordion-arrow {
        transform: rotate(180deg);
        color: var(--text-main);
    }

    /* Inner File List */
    .file-list-group {
        padding-left: 65px; /* Indent to align with text */
        padding-bottom: 15px;
    }

    .file-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px dashed #e9ecef;
    }
    
    .file-row:last-child { border-bottom: none; }

    .file-name {
        font-size: 0.85rem;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btn-sm-download {
        font-size: 0.7rem;
        padding: 2px 8px;
        border: 1px solid #d2d6da;
        border-radius: 4px;
        color: var(--text-main);
        text-decoration: none;
    }

    /* --- Footer Navigation --- */
    .footer-nav {
        margin-top: 15px;
        text-align: center;
        color: #adb5bd;
        flex-shrink: 0;
    }
    
    .footer-text {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
        color: #8392ab;
        text-transform: uppercase;
    }

    .btn-nav-arrow {
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
    .btn-nav-arrow:hover {
        background: #e9ecef;
        color: var(--text-main);
    }
</style>
@endsection

@section('content')
<div class="content-wrapper">
    <div class="container-fluid">

        <div class="page-header-row">
            <h1 class="page-title">NSE Member Segment</h1>
            <div class="header-right">
                <button class="btn-sync" onclick="syncNow('{{ $segment }}', '{{ $folder }}')">
                    <i class="fas fa-sync-alt"></i> SYNC NOW
                </button>
            </div>
        </div>

        <div class="custom-breadcrumb mb-2">
            <span>NSE</span>
            <span class="breadcrumb-separator">/</span>
            <span>Member Segment</span>
            <span class="breadcrumb-separator">/</span>
            <span>{{ str()->upper($segment) }}</span>
            <span class="breadcrumb-separator">/</span>
            <span class="active-item">{{ str()->studly($folder) }} Archives</span>
        </div>

        <div class="white-card">
            
            <div class="card-heading">
                <i class="fas fa-history"></i> Archive History
            </div>

            <div class="archive-list-container">
                
                @php
                    $groupedContents = $contents->groupBy(function($item) {
                        return $item->nse_modified_at->format('Y-m-d');
                    });
                @endphp

                @forelse($groupedContents as $date => $files)
                    @php
                        $carbonDate = \Carbon\Carbon::parse($date);
                        $day = $carbonDate->format('d');
                        $fullDate = $carbonDate->format('d M Y');
                        $collapseId = 'collapse-' . $carbonDate->timestamp;
                    @endphp

                    <div class="archive-item">
                        <div class="archive-header" data-toggle="collapse" data-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                            
                            <div class="date-badge">
                                {{ $day }}
                            </div>

                            <div class="archive-info">
                                <div class="archive-date-title">{{ $fullDate }}</div>
                                <div class="archive-meta">{{ $files->count() }} Files generated</div>
                            </div>

                            <i class="fas fa-chevron-down accordion-arrow"></i>
                        </div>

                        <div id="{{ $collapseId }}" class="collapse">
                            <div class="file-list-group">
                                @foreach($files as $file)
                                    <div class="file-row">
                                        <div class="file-name">
                                            <i class="{{ $file->type == 'folder' ? 'far fa-folder' : 'far fa-file-alt' }} text-muted"></i>
                                            {{ $file->name }}
                                        </div>
                                        <div>
                                            <button onclick="triggerDownload(this, {{ $file->id }})" class="btn-sm-download bg-light">Download</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                @empty
                    <div class="text-center py-5">
                        <p class="text-muted">No archive history found.</p>
                    </div>
                @endforelse

            </div>

            <div class="footer-nav">
                <a href="{{ route('nse.segment.folder.today', ['segment' => $segment, 'folder' => $folder]) }}" class="btn-nav-arrow">
                    <i class="fas fa-arrow-up"></i>
                </a>
                <div class="footer-text mt-2">BACK TO TODAY'S ACTIVITY</div>
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

    function syncNow(segment, folder) {
        const btn = document.querySelector('.btn-sync');
        const originalHtml = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> REFRESHING...';

        const url = "{{ route('nse.archive.sync.clear', ['segment' => ':segment', 'folder' => ':folder']) }}".replace(':segment', segment).replace(':folder', folder);

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Failed to clear cache.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Something went wrong.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
    }
</script>
@endsection