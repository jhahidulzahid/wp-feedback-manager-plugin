/**
 * Frontend JavaScript for Feedback Manager
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        const form = $('#feedback-form');
        const messageDiv = $('#feedback-message');
        const submitBtn = form.find('.feedback-submit-btn');
        const btnText = submitBtn.find('.btn-text');
        const btnLoading = submitBtn.find('.btn-loading');
        
        // Form validation
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        function showMessage(message, type) {
            messageDiv
                .removeClass('success error')
                .addClass(type)
                .html(message)
                .slideDown();
            
            // Scroll to message
            $('html, body').animate({
                scrollTop: messageDiv.offset().top - 100
            }, 500);
            
            // Auto-hide after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(function() {
                    messageDiv.slideUp();
                }, 5000);
            }
        }
        
        function setLoading(isLoading) {
            if (isLoading) {
                submitBtn.prop('disabled', true);
                btnText.hide();
                btnLoading.show();
            } else {
                submitBtn.prop('disabled', false);
                btnText.show();
                btnLoading.hide();
            }
        }
        
        // Handle form submission
        form.on('submit', function(e) {
            e.preventDefault();
            
            // Hide previous messages
            messageDiv.slideUp();
            
            // Get form values
            const name = $('#feedback-name').val().trim();
            const email = $('#feedback-email').val().trim();
            const message = $('#feedback-message-field').val().trim();
            
            // Validate
            if (!name) {
                showMessage(feedbackManager.strings.required + ' (Name)', 'error');
                $('#feedback-name').focus();
                return;
            }
            
            if (!email) {
                showMessage(feedbackManager.strings.required + ' (Email)', 'error');
                $('#feedback-email').focus();
                return;
            }
            
            if (!validateEmail(email)) {
                showMessage(feedbackManager.strings.invalidEmail, 'error');
                $('#feedback-email').focus();
                return;
            }
            
            if (!message) {
                showMessage(feedbackManager.strings.required + ' (Message)', 'error');
                $('#feedback-message-field').focus();
                return;
            }
            
            // Set loading state
            setLoading(true);
            
            // Prepare data
            const formData = {
                name: name,
                email: email,
                message: message,
                nonce: feedbackManager.nonce
            };
            
            // Submit via REST API
            $.ajax({
                url: feedbackManager.ajaxUrl,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(formData),
                beforeSend: function(xhr) {
                    // Add REST nonce to header for additional security
                    if (feedbackManager.restNonce) {
                        xhr.setRequestHeader('X-WP-Nonce', feedbackManager.restNonce);
                    }
                },
                success: function(response) {
                    setLoading(false);
                    
                    if (response.success || response.message) {
                        showMessage(response.message || feedbackManager.strings.success, 'success');
                        form[0].reset();
                        // Reset character counter
                        $('.char-counter .current').text('0');
                    } else {
                        showMessage(feedbackManager.strings.error, 'error');
                    }
                },
                error: function(xhr) {
                    setLoading(false);
                    
                    let errorMessage = feedbackManager.strings.error;
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    }
                    
                    showMessage(errorMessage, 'error');
                }
            });
        });
        
        // Real-time validation feedback
        $('#feedback-email').on('blur', function() {
            const email = $(this).val().trim();
            if (email && !validateEmail(email)) {
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });
        
        // Character counter for message field
        const messageField = $('#feedback-message-field');
        const maxLength = 5000;
        
        messageField.after('<div class="char-counter"><span class="current">0</span> / ' + maxLength + '</div>');
        
        messageField.on('input', function() {
            const currentLength = $(this).val().length;
            $(this).next('.char-counter').find('.current').text(currentLength);
            
            if (currentLength > maxLength * 0.9) {
                $(this).next('.char-counter').addClass('warning');
            } else {
                $(this).next('.char-counter').removeClass('warning');
            }
        });
    });
    
})(jQuery);