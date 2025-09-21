// Global App Functions
$(document).ready(function() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
    
    // Add loading state to forms
    $('form').on('submit', function() {
        $(this).find('button[type="submit"]').prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
    });
});

// Utility Functions
function showAlert(type, message, container = 'body') {
    const alertId = 'alert-' + Date.now();
    const alertHtml = `
        <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 1060; min-width: 300px;" role="alert">
            <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'danger' ? 'exclamation-triangle' : 'info-circle')} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $(container).append(alertHtml);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        $(`#${alertId}`).fadeOut(() => {
            $(`#${alertId}`).remove();
        });
    }, 5000);
}

function formatMoney(amount) {
    return 'Rs. ' + parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}


function timeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'just now';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
    if (diffInSeconds < 2592000) return Math.floor(diffInSeconds / 86400) + ' days ago';
    
    return date.toLocaleDateString();
}

function validateForm(formSelector) {
    let isValid = true;
    
    $(formSelector + ' [required]').each(function() {
        const field = $(this);
        const value = field.val().trim();
        
        if (!value) {
            field.addClass('is-invalid');
            isValid = false;
        } else {
            field.removeClass('is-invalid');
        }
    });
    
    // Email validation
    $(formSelector + ' input[type="email"]').each(function() {
        const email = $(this).val();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email && !emailRegex.test(email)) {
            $(this).addClass('is-invalid');
            isValid = false;
        }
    });
    
    return isValid;
}

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// AJAX Helper
// AJAX Helper - Updated to return the promise
function makeAjaxRequest(url, data, successCallback, errorCallback) {
    return $.ajax({
        url: url,
        method: 'POST',
        data: data,
        dataType: 'json',
        timeout: 30000,
        success: function(response) {
            if (response.status === 'success') {
                if (successCallback) successCallback(response);
            } else {
                if (errorCallback) {
                    errorCallback(response.message || 'An error occurred');
                } else {
                    showAlert('danger', response.message || 'An error occurred');
                }
            }
        },
        error: function(xhr, status, error) {
            let message;
            if (status === 'timeout') {
                message = 'Request timed out. Please try again.';
            } else if (xhr.status === 0) {
                message = 'Network error. Please check your connection.';
            } else {
                message = `Server error (${xhr.status}). Please try again.`;
            }
            
            if (errorCallback) {
                errorCallback(message);
            } else {
                showAlert('danger', message);
            }
        }
    }); // This returns the jQuery AJAX promise
}



// Auto-refresh functions
function startAutoRefresh(callback, interval = 30000) {
    setInterval(callback, interval);
}

// File upload helper
function handleFileUpload(fileInput, previewContainer) {
    $(fileInput).on('change', function() {
        const files = this.files;
        $(previewContainer).empty();
        
        Array.from(files).forEach(file => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = `
                        <div class="col-md-3 mb-3">
                            <img src="${e.target.result}" class="img-fluid rounded" style="height: 150px; object-fit: cover;">
                            <div class="text-center mt-2">
                                <small class="text-muted">${file.name}</small>
                            </div>
                        </div>
                    `;
                    $(previewContainer).append(preview);
                };
                reader.readAsDataURL(file);
            }
        });
    });
}

// Initialize common functionality
function initializeApp() {
    // Add fade-in animation to cards
    $('.card, .dashboard-card, .job-card').addClass('fade-in-up');
    
    // Add hover effects
    $('.card-hover').hover(
        function() { $(this).addClass('shadow-lg'); },
        function() { $(this).removeClass('shadow-lg'); }
    );
    
    // Form validation on submit
    $('form').on('submit', function(e) {
        const form = $(this);
        if (form.hasClass('needs-validation') && !validateForm(`#${form.attr('id')}`)) {
            e.preventDefault();
            showAlert('warning', 'Please fill in all required fields correctly.');
        }
    });
}

// Initialize when document is ready
$(document).ready(function() {
    initializeApp();
});


$(document).ajaxComplete(function(event, xhr, settings) {
    // Reset any buttons that might be stuck in loading state after 2 seconds
    setTimeout(function() {
        $('button:disabled').each(function() {
            const $btn = $(this);
            const btnHtml = $btn.html();
            
            // Check if button is stuck in loading state
            if (btnHtml.includes('fa-spinner') || btnHtml.includes('Processing') || btnHtml.includes('Updating')) {
                $btn.prop('disabled', false);
                
                // Try to restore original text if available
                if ($btn.data('original-text')) {
                    $btn.html($btn.data('original-text'));
                } else {
                    // Fallback to reasonable defaults
                    if (btnHtml.includes('Updating')) {
                        $btn.html('<i class="fas fa-save me-2"></i>Update Information');
                    } else if (btnHtml.includes('Processing')) {
                        $btn.html('<i class="fas fa-save me-2"></i>Save Changes');
                    } else {
                        $btn.html('Submit');
                    }
                }
                
                console.log('Reset stuck button:', $btn);
            }
        });
    }, 2000);
});


$(document).ajaxError(function(event, xhr, settings, thrownError) {
    setTimeout(function() {
        $('button:disabled').each(function() {
            const $btn = $(this);
            if ($btn.html().includes('fa-spinner')) {
                $btn.prop('disabled', false);
                if ($btn.data('original-text')) {
                    $btn.html($btn.data('original-text'));
                }
            }
        });
    }, 500);
});

