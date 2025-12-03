/**
 * Book Submission Modal JavaScript
 * Handles modal interactions and AJAX requests for book submission
 */

(function($) {
    'use strict';

    // Modal instances
    var submitModal = null;
    var pendingModal = null;

    // Initialize on document ready
    $(document).ready(function() {
        initializeModals();
        attachEventHandlers();
    });

    /**
     * Initialize modal elements
     */
    function initializeModals() {
        submitModal = $('#hs-book-submit-modal');
        pendingModal = $('#hs-pending-books-modal');
    }

    /**
     * Attach event handlers
     */
    function attachEventHandlers() {
        // Open submit book modal
        $(document).on('click', '.hs-submit-book-trigger', function(e) {
            e.preventDefault();
            openSubmitModal();
        });

        // Open pending books modal
        $(document).on('click', '.hs-my-submissions-trigger', function(e) {
            e.preventDefault();
            openPendingModal();
        });

        // Close modals
        $(document).on('click', '.hs-book-modal-close', function() {
            closeSubmitModal();
        });

        $(document).on('click', '.hs-pending-modal-close', function() {
            closePendingModal();
        });

        // Close on outside click
        $(window).on('click', function(e) {
            if ($(e.target).hasClass('hs-book-submit-modal')) {
                closeSubmitModal();
            }
            if ($(e.target).hasClass('hs-pending-books-modal')) {
                closePendingModal();
            }
        });

        // Close on escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSubmitModal();
                closePendingModal();
            }
        });

        // External ID type change
        $('#hs-book-external-id-type').on('change', function() {
            toggleExternalIdField();
        });

        // Submit book form
        $('#hs-book-submit-form').on('submit', function(e) {
            e.preventDefault();
            handleBookSubmit();
        });

        // View rejection reason
        $(document).on('click', '.hs-view-reason', function() {
            var reason = $(this).data('reason');
            alert('Rejection reason:\n\n' + reason);
        });
    }

    /**
     * Open submit book modal
     */
    function openSubmitModal() {
        submitModal.addClass('active').fadeIn(300);
        $('body').css('overflow', 'hidden');
    }

    /**
     * Close submit book modal
     */
    function closeSubmitModal() {
        submitModal.removeClass('active').fadeOut(300);
        $('body').css('overflow', '');
        clearSubmitForm();
    }

    /**
     * Open pending books modal and load submissions
     */
    function openPendingModal() {
        pendingModal.addClass('active').fadeIn(300);
        $('body').css('overflow', 'hidden');
        loadPendingBooks();
    }

    /**
     * Close pending books modal
     */
    function closePendingModal() {
        pendingModal.removeClass('active').fadeOut(300);
        $('body').css('overflow', '');
    }

    /**
     * Toggle external ID field visibility
     */
    function toggleExternalIdField() {
        var type = $('#hs-book-external-id-type').val();
        var $group = $('#hs-external-id-group');
        var $help = $('#hs-external-id-help');

        if (type && type !== '') {
            $group.show();

            var helpText = {
                'ASIN': 'Example: B08N5WRWNW (Amazon product ID)',
                'OCLC': 'Example: 123456789 (WorldCat number)',
                'LCCN': 'Example: 2020123456',
                'Goodreads': 'Example: 12345678 (from the Goodreads URL)',
                'Other': 'Enter the identifier'
            };
            $help.text(helpText[type] || '');
        } else {
            $group.hide();
        }
    }

    /**
     * Handle book submission
     */
    function handleBookSubmit() {
        var $form = $('#hs-book-submit-form');
        var $button = $('#hs-book-submit-btn');
        var $messageArea = $('#hs-book-message-area');
        var addToLibrary = $('#hs-add-to-library').is(':checked');

        // Disable button
        $button.prop('disabled', true).text('Submitting...');
        $messageArea.empty();

        var formData = {
            title: $('#hs-book-title').val(),
            author: $('#hs-book-author').val(),
            page_count: $('#hs-book-page-count').val(),
            description: $('#hs-book-description').val(),
            cover_url: $('#hs-book-cover-url').val(),
            publication_year: $('#hs-book-publication-year').val(),
            publisher: $('#hs-book-publisher').val(),
            external_id_type: $('#hs-book-external-id-type').val(),
            external_id: $('#hs-book-external-id').val()
        };

        // Submit the book
        $.ajax({
            type: 'POST',
            url: hsBookSubmission.restUrl + 'gread/v1/books/submit',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', hsBookSubmission.nonce);
            },
            success: function(response) {
                if (response.success) {
                    var pendingBookId = response.data.id;
                    var message = response.message;

                    // If user wants to add to library, do that next
                    if (addToLibrary) {
                        addPendingBookToLibrary(pendingBookId, function(success) {
                            if (success) {
                                message += ' The book has been added to your library!';
                            } else {
                                message += ' However, there was an issue adding it to your library.';
                            }
                            displayMessage('success', message);
                            $form[0].reset();
                        });
                    } else {
                        displayMessage('success', message);
                        $form[0].reset();
                    }
                } else {
                    displayMessage('error', response.message || 'Failed to submit book');
                }
            },
            error: function(xhr) {
                var errorMessage = 'An error occurred while submitting the book.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                displayMessage('error', errorMessage);
            },
            complete: function() {
                $button.prop('disabled', false).text('Submit Book');
            }
        });
    }

    /**
     * Add pending book to user's library
     */
    function addPendingBookToLibrary(pendingBookId, callback) {
        $.ajax({
            type: 'POST',
            url: hsBookSubmission.restUrl + 'gread/v1/pending-books/' + pendingBookId + '/add-to-library',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', hsBookSubmission.nonce);
            },
            success: function(response) {
                callback(response.success);
            },
            error: function() {
                callback(false);
            }
        });
    }

    /**
     * Load pending books for current user
     */
    function loadPendingBooks() {
        var $container = $('#hs-pending-books-list');
        $container.html('<div class="hs-no-submissions">Loading your submissions...</div>');

        $.ajax({
            type: 'GET',
            url: hsBookSubmission.restUrl + 'gread/v1/pending-books/my-submissions',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', hsBookSubmission.nonce);
            },
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    renderPendingBooks(response.data);
                } else {
                    $container.html('<div class="hs-no-submissions">You haven\'t submitted any books yet. Click "Submit a Book" to get started!</div>');
                }
            },
            error: function() {
                $container.html('<div class="hs-no-submissions">Error loading submissions. Please try again.</div>');
            }
        });
    }

    /**
     * Render pending books table
     */
    function renderPendingBooks(books) {
        var $container = $('#hs-pending-books-list');
        var html = '<table class="hs-pending-books-table">';
        html += '<thead><tr>';
        html += '<th>Title</th>';
        html += '<th>Author</th>';
        html += '<th>Status</th>';
        html += '<th>Submitted</th>';
        html += '<th>Actions</th>';
        html += '</tr></thead><tbody>';

        books.forEach(function(book) {
            var statusClass = book.status || 'pending';
            var statusText = {
                'pending': '⏳ Pending Review',
                'approved': '✓ Approved',
                'rejected': '✗ Rejected'
            }[statusClass] || statusClass;

            html += '<tr>';
            html += '<td><div class="hs-book-title">' + escapeHtml(book.title) + '</div></td>';
            html += '<td>' + escapeHtml(book.author) + '</td>';
            html += '<td><span class="hs-status-badge ' + statusClass + '">' + statusText + '</span></td>';
            html += '<td>' + formatDate(book.submitted_at) + '</td>';
            html += '<td>';

            if (book.status === 'approved' && book.approved_book_id) {
                html += '<a href="?p=' + book.approved_book_id + '" class="hs-book-button hs-book-button-primary" style="padding: 6px 12px; font-size: 12px;">View Book</a>';
            } else if (book.status === 'rejected' && book.rejection_reason) {
                html += '<button class="hs-view-reason hs-book-button hs-book-button-secondary" style="padding: 6px 12px; font-size: 12px;" data-reason="' + escapeHtml(book.rejection_reason) + '">View Reason</button>';
            } else {
                html += '<span style="color: #999;">—</span>';
            }

            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $container.html(html);
    }

    /**
     * Display message in submit form
     */
    function displayMessage(type, message) {
        var $messageArea = $('#hs-book-message-area');
        var html = '<div class="hs-book-message hs-book-message-' + type + '">' + message + '</div>';
        $messageArea.html(html);

        // Scroll to message
        $messageArea[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /**
     * Clear submit form
     */
    function clearSubmitForm() {
        $('#hs-book-submit-form')[0].reset();
        $('#hs-book-message-area').empty();
        $('#hs-external-id-group').hide();
    }

    /**
     * Format date for display
     */
    function formatDate(dateString) {
        var date = new Date(dateString);
        var options = { year: 'numeric', month: 'short', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

})(jQuery);
