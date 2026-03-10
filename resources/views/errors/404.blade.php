@extends('layouts.user_type.auth')

@section('page_title', __('Page Not Found'))

@section('content')
<style>
    :root {
        --brand-color: #4f46e5; /* Adjust to match your project's indigo */
        --brand-hover: #4338ca;
        --text-main: #1f2937;
        --text-muted: #6b7280;
        --bg-light: #f9fafb;
    }

    .error-container {
        min-height: 70vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        font-family: 'Inter', system-ui, sans-serif;
    }

    .error-content {
        max-width: 500px;
        width: 100%;
        text-align: center;
    }

    .error-code-wrapper {
        position: relative;
        margin-bottom: 2rem;
    }

    .error-code {
        font-size: 8rem;
        font-weight: 900;
        color: #f3f4f6;
        line-height: 1;
        margin: 0;
        user-select: none;
    }

    .error-icon-overlay {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 1rem;
        border-radius: 50%;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .error-title {
        font-size: 1.875rem;
        font-weight: 700;
        color: var(--text-main);
        margin-bottom: 1rem;
    }

    .error-message {
        color: var(--text-muted);
        line-height: 1.6;
        margin-bottom: 2.5rem;
    }

    .button-group {
        display: flex;
        gap: 1rem;
        justify-content: center;
    }

    .btn-custom {
        padding: 0.75rem 1.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        border-radius: 10px;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
    }

    .btn-outline {
        background: white;
        color: var(--text-main);
        border: 1px solid #d1d5db;
    }

    .btn-outline:hover {
        background: var(--bg-light);
        border-color: #9ca3af;
    }

    .btn-primary-custom {
        background: var(--brand-color);
        color: white;
        border: none;
        box-shadow: 0 4px 14px 0 rgba(79, 70, 229, 0.3);
    }

    .btn-primary-custom:hover {
        background: var(--brand-hover);
        transform: translateY(-1px);
    }

    .btn-primary-custom:active {
        transform: scale(0.98);
    }

    @media (max-width: 640px) {
        .button-group {
            flex-direction: column;
        }
        .error-code {
            font-size: 6rem;
        }
    }
</style>

<div class="error-container">
    <div class="error-content">
        <div class="error-code-wrapper">
            <h1 class="error-code">404</h1>
            <div class="error-icon-overlay">
                <i data-lucide="compass" style="width: 48px; height: 48px; color: var(--brand-color);"></i>
            </div>
        </div>

        <h2 class="error-title">Page not found</h2>
        <p class="error-message">
            Sorry, we couldn't find the page you're looking for. It might have been moved or deleted.
        </p>

        <div class="button-group">
            <a href="{{ url()->previous() }}" class="btn-custom btn-outline">
                <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i>
                Go Back
            </a>
            <a href="{{ route('home') }}" class="btn-custom btn-primary-custom">
                <i data-lucide="home" style="width: 16px; height: 16px;"></i>
                Return Home
            </a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>
@endsection