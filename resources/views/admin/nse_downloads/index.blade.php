@extends('layouts.user_type.auth')

@section('page_title', __('NSE Extranet Auto Downloader'))

@section('style')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<style>
    #urlInputContainer {
        max-height: 60vh;
        overflow-y: auto;
    }
</style>
@endsection

@section('header-title')
<span>NSE Member Segment</span>
@endsection

@section('header-timer')
<strong style="font-size: 12px; color: red;" id="countdown"></strong>
@endsection

@section('content')
<main class="flex-1 p-6 bg-gray-50">
    <nav class="p-2 text-sm font-medium text-gray-600">
        <ol class="flex items-center gap-2 flex-wrap">
            <li>NSE</li>
            <li class="text-gray-400">/</li>
            <li>Member Segment</li>
            <li class="text-gray-400">/</li>
            <li>Auto Downloader</li>
        </ol>
    </nav>

    <div class="bg-white rounded-lg shadow-lg mt-4">
        <div class="px-6 py-3 border-b border-gray-200 flex justify-between items-center">
            <div class="flex items-center gap-3 text-lg font-bold text-gray-900">
                <i data-lucide="download-cloud" class="w-6 h-6 text-blue-500"></i>
                Auto Download Configurations
            </div>
        </div>

        <div class="p-6 relative overflow-x-auto">
            <form id="autoDownloadForm" novalidate>
                @csrf
                <div id="urlInputContainer" class="space-y-4 pr-2">

                    @forelse($downloads as $download)
                    <div class="flex items-center gap-3 url-row">
                        <div class="flex-1">
                            <input type="url"
                                name="urls[]"
                                value="{{ $download->url }}"
                                placeholder="https://example.com/file.zip"
                                class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                                required>
                        </div>
                        <button type="button" class="remove-btn text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-md transition-colors" title="Remove URL">
                            <i data-lucide="trash-2" class="w-5 h-5"></i>
                        </button>
                    </div>
                    @empty
                    <div class="flex items-center gap-3 url-row">
                        <div class="flex-1">
                            <input type="url"
                                name="urls[]"
                                placeholder="https://example.com/file.zip"
                                class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                                required>
                        </div>
                        <button type="button" class="remove-btn text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-md transition-colors hidden" title="Remove URL">
                            <i data-lucide="trash-2" class="w-5 h-5"></i>
                        </button>
                    </div>
                    @endforelse

                </div>

                <div class="mt-6 flex flex-wrap gap-3 pt-4 border-t border-gray-100">
                    <button type="button" id="addUrlBtn" class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors border border-gray-300 font-medium">
                        <i data-lucide="plus" class="w-4 h-4"></i> Add Another URL
                    </button>

                    <button type="submit" id="saveBtn" class="flex items-center gap-2 px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors font-medium ml-auto shadow-sm">
                        <i data-lucide="save" class="w-4 h-4"></i> Save Paths
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>
@endsection

@section('script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
    $(document).ready(function() {

        // Configure Toastr Options
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "4000",
        };

        // 1. Handle adding new URL rows
        $('#addUrlBtn').click(function() {
            let newRow = `
            <div class="flex items-center gap-3 url-row">
                <div class="flex-1">
                    <input type="url" 
                           name="urls[]" 
                           placeholder="https://example.com/file.zip" 
                           class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all" 
                           required>
                </div>
                <button type="button" class="remove-btn text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-md transition-colors" title="Remove URL">
                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                </button>
            </div>
        `;

            $('#urlInputContainer').append(newRow);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            updateRemoveButtons();
        });

        // 2. Handle removing URL rows
        $(document).on('click', '.remove-btn', function() {
            $(this).closest('.url-row').remove();
            updateRemoveButtons();
        });

        // 3. Logic to hide the remove button if there is only one input row
        function updateRemoveButtons() {
            let rows = $('.url-row');
            if (rows.length > 1) {
                rows.find('.remove-btn').removeClass('hidden');
            } else {
                rows.find('.remove-btn').addClass('hidden');
            }
        }

        updateRemoveButtons();

        // 4. Validate String / URL format
        function isValidUrlString(string) {
            try {
                new URL(string);
                return true;
            } catch (err) {
                return false;
            }
        }

        function showCustomToast(type, message, title = null) {
            let container = document.getElementById('custom-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'custom-toast-container';
                container.className = 'fixed top-4 right-4 z-[9999] flex flex-col gap-3 pointer-events-none';
                document.body.appendChild(container);
            }

            const isError = type === 'error';
            const displayTitle = title || (isError ? 'Error' : 'Success');

            const iconSvg = isError ?
                `<svg class="w-10 h-10 text-red-600 shadow-sm rounded-full bg-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>` :
                `<svg class="w-10 h-10 text-green-500 shadow-sm rounded-full bg-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>`;

            const titleColor = isError ? 'text-red-600' : 'text-green-600';

            // 3. Create the toast element
            const toast = document.createElement('div');
            // Using Tailwind for styling to match your screenshot
            toast.className = 'bg-white rounded-lg shadow-[0_4px_20px_rgba(0,0,0,0.12)] border border-gray-100 p-4 flex items-center w-80 pointer-events-auto transform transition-all duration-300 translate-x-[120%] opacity-0';

            toast.innerHTML = `
                <div class="flex-shrink-0">
                    ${iconSvg}
                </div>
                <div class="ml-4 flex-1">
                    <p class="text-base font-bold ${titleColor} mb-0.5 leading-none">${displayTitle}</p>
                    <p class="text-sm text-gray-700 leading-tight">${message}</p>
                </div>
                <button class="ml-auto text-gray-400 hover:text-gray-600 transition-colors self-start focus:outline-none" onclick="this.parentElement.style.opacity='0'; setTimeout(() => this.parentElement.remove(), 300)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            `;

            container.appendChild(toast);

            // 4. Trigger the slide-in animation
            requestAnimationFrame(() => {
                toast.classList.remove('translate-x-[120%]', 'opacity-0');
            });

            // 5. Auto-remove after 4 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.add('translate-x-[120%]', 'opacity-0');
                    setTimeout(() => toast.remove(), 300);
                }
            }, 4000);
        }

        // 5. Handle AJAX Form Submission
        $('#autoDownloadForm').submit(function(e) {
            e.preventDefault();

            let form = $(this);
            let saveBtn = $('#saveBtn');
            let isValid = true;
            let errorMessage = '';

            $('input[name="urls[]"]').each(function() {
                let val = $(this).val().trim();
                if (val === '') {
                    isValid = false;
                    errorMessage = 'Please fill in all URL fields or remove empty ones.';
                    $(this).addClass('border-red-500');
                } else {
                    $(this).removeClass('border-red-500');
                }
            });

            if (!isValid) {
                // Trigger JS Validation Error
                showCustomToast('error', errorMessage, 'Validation Error');
                return;
            }

            $.ajax({
                url: '{{ route("nse.downloads.save") }}',
                type: 'POST',
                data: form.serialize(),
                beforeSend: function() {
                    saveBtn.prop('disabled', true).html('Saving...');
                },
                success: function(response) {
                    if (response.success) {
                        // Trigger Success Toast
                        showCustomToast('success', response.message || 'URLs saved successfully!', 'Success');
                    } else {
                        // Trigger Logic Error Toast
                        showCustomToast('error', response.message || 'Something went wrong.', 'Error');
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'An error occurred while communicating with the server.';
                    if (xhr.status === 422 && xhr.responseJSON.errors) {
                        errorMsg = Object.values(xhr.responseJSON.errors)[0][0];
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }

                    // Trigger Server Error Toast
                    showCustomToast('error', errorMsg, 'Error');
                },
                complete: function() {
                    saveBtn.prop('disabled', false).html('Save Paths');
                }
            });
        });
    });
</script>
@endsection