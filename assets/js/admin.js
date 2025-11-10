/**
 * Admin JavaScript for Feedback Manager
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Select all checkboxes functionality
        $('#select-all-1, #select-all-2').on('change', function() {
            const isChecked = $(this).prop('checked');
            $('input[name="feedback_ids[]"]').prop('checked', isChecked);
            $('#select-all-1, #select-all-2').prop('checked', isChecked);
        });
        
        // Individual checkbox change
        $('input[name="feedback_ids[]"]').on('change', function() {
            const totalCheckboxes = $('input[name="feedback_ids[]"]').length;
            const checkedCheckboxes = $('input[name="feedback_ids[]"]:checked').length;
            $('#select-all-1, #select-all-2').prop('checked', totalCheckboxes === checkedCheckboxes);
        });
        
        // Bulk delete confirmation
        $('#bulk-action-form').on('submit', function(e) {
            const action = $('#bulk-action-selector-top').val();
            
            if (action === 'bulk_delete') {
                const checkedCount = $('input[name="feedback_ids[]"]:checked').length;
                
                if (checkedCount === 0) {
                    e.preventDefault();
                    alert('Please select at least one feedback to delete.');
                    return false;
                }
                
                if (!confirm(feedbackManagerAdmin.strings.confirmBulkDelete)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        // Delete single feedback
        $('.delete-feedback').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(feedbackManagerAdmin.strings.confirmDelete)) {
                return;
            }
            
            const btn = $(this);
            const feedbackId = btn.data('id');
            const row = btn.closest('tr');
            
            // Disable button
            btn.prop('disabled', true).text('Deleting...');
            
            $.ajax({
                url: feedbackManagerAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'delete_feedback',
                    id: feedbackId,
                    nonce: feedbackManagerAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Fade out and remove row
                        row.fadeOut(400, function() {
                            $(this).remove();
                            
                            // Check if table is empty
                            if ($('.feedback-table tbody tr').length === 0) {
                                location.reload();
                            }
                        });
                        
                        // Show success message
                        showNotice(feedbackManagerAdmin.strings.deleteSuccess, 'success');
                    } else {
                        btn.prop('disabled', false).text('Delete');
                        showNotice(response.data.message || feedbackManagerAdmin.strings.deleteError, 'error');
                    }
                },
                error: function() {
                    btn.prop('disabled', false).text('Delete');
                    showNotice(feedbackManagerAdmin.strings.deleteError, 'error');
                }
            });
            $('.feedback-stats-wrapper').load(location.href + ' .feedback-stats-wrapper > *');
        });
        
        // View full message modal
        $('.view-full-message').on('click', function(e) {
            e.preventDefault();
            const message = $(this).data('message');
            $('#full-message-text').text(message);
            $('#full-message-modal').fadeIn(300);
        });
        
        // Close modal
        $('.feedback-modal-close, .feedback-modal-overlay').on('click', function() {
            $('#full-message-modal').fadeOut(300);
        });
        
        // Close modal on ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('#full-message-modal').fadeOut(300);
            }
        });
        
        // Helper function to show notices
        function showNotice(message, type) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after(notice);
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                notice.fadeOut(400, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Make dismissible
            notice.on('click', '.notice-dismiss', function() {
                notice.fadeOut(400, function() {
                    $(this).remove();
                });
            });
        }
        
        // Add tooltips for truncated messages
        $('.message-preview').each(function() {
            const $this = $(this);
            if (this.offsetWidth < this.scrollWidth) {
                $this.attr('title', $this.text());
            }
        });
        
    });
    
})(jQuery);