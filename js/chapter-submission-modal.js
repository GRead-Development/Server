/**
 * Chapter Submission Modal JavaScript
 * Handles the modal for submitting chapter information for books
 */

jQuery(document).ready(function($) {
    let chapterCount = 0;

    // Open chapter submission modal
    $(document).on('click', '.hs-submit-chapters-btn', function(e) {
        e.preventDefault();
        const bookId = $(this).data('book-id');
        openChapterSubmissionModal(bookId);
    });

    // Close modal
    $(document).on('click', '.hs-chapters-modal-close, .hs-chapters-modal-overlay', function() {
        closeChapterSubmissionModal();
    });

    // Prevent closing when clicking inside modal
    $(document).on('click', '.hs-chapters-modal-content', function(e) {
        e.stopPropagation();
    });

    // Add chapter row
    $(document).on('click', '#hs-add-chapter-row', function() {
        addChapterRow();
    });

    // Remove chapter row
    $(document).on('click', '.hs-remove-chapter', function() {
        $(this).closest('.hs-chapter-row').fadeOut(200, function() {
            $(this).remove();
            updateChapterNumbers();
        });
    });

    // Submit chapters
    $(document).on('click', '#hs-submit-chapters-btn', function() {
        submitChapters();
    });

    // Enter key to add new row (when in title field)
    $(document).on('keypress', '.hs-chapter-title', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            addChapterRow();
            // Focus on the next chapter title field
            $('.hs-chapter-title').last().focus();
        }
    });

    /**
     * Open the chapter submission modal
     */
    function openChapterSubmissionModal(bookId) {
        const modalHTML = `
            <div class="hs-chapters-modal-overlay">
                <div class="hs-chapters-modal-content">
                    <span class="hs-chapters-modal-close">&times;</span>
                    <h2>Submit Chapter Information</h2>
                    <p>Help other readers by providing chapter names for this book!</p>
                    <div id="hs-chapters-form">
                        <div id="hs-chapters-list">
                            <!-- Chapter rows will be added here -->
                        </div>
                        <button type="button" id="hs-add-chapter-row" class="hs-button-secondary">
                            + Add Chapter
                        </button>
                        <div class="hs-chapters-actions">
                            <button type="button" id="hs-submit-chapters-btn" class="hs-button-primary" data-book-id="${bookId}">
                                Submit for Review
                            </button>
                            <button type="button" class="hs-chapters-modal-close hs-button-secondary">
                                Cancel
                            </button>
                        </div>
                        <div id="hs-chapters-message"></div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHTML);
        chapterCount = 0;

        // Add initial chapter rows
        addChapterRow();
        addChapterRow();
        addChapterRow();

        // Focus on first chapter title
        setTimeout(function() {
            $('.hs-chapter-title').first().focus();
        }, 100);
    }

    /**
     * Close the chapter submission modal
     */
    function closeChapterSubmissionModal() {
        $('.hs-chapters-modal-overlay').fadeOut(200, function() {
            $(this).remove();
        });
        chapterCount = 0;
    }

    /**
     * Add a new chapter row
     */
    function addChapterRow() {
        chapterCount++;
        const rowHTML = `
            <div class="hs-chapter-row">
                <div class="hs-chapter-number-wrapper">
                    <label>Chapter #</label>
                    <input type="number"
                           class="hs-chapter-number"
                           value="${chapterCount}"
                           min="1"
                           placeholder="#"
                           required>
                </div>
                <div class="hs-chapter-title-wrapper">
                    <label>Chapter Title</label>
                    <input type="text"
                           class="hs-chapter-title"
                           placeholder="e.g., The Beginning"
                           required>
                </div>
                <button type="button" class="hs-remove-chapter" title="Remove chapter">
                    &times;
                </button>
            </div>
        `;

        $('#hs-chapters-list').append(rowHTML);
    }

    /**
     * Update chapter numbers after removal
     */
    function updateChapterNumbers() {
        let index = 1;
        $('.hs-chapter-row').each(function() {
            $(this).find('.hs-chapter-number').val(index);
            index++;
        });
        chapterCount = index - 1;
    }

    /**
     * Submit chapters to the API
     */
    function submitChapters() {
        const bookId = $('#hs-submit-chapters-btn').data('book-id');
        const chapters = [];
        let hasError = false;

        // Collect chapter data
        $('.hs-chapter-row').each(function() {
            const number = parseInt($(this).find('.hs-chapter-number').val());
            const title = $(this).find('.hs-chapter-title').val().trim();

            if (!title) {
                hasError = true;
                $(this).find('.hs-chapter-title').addClass('hs-error');
                return;
            } else {
                $(this).find('.hs-chapter-title').removeClass('hs-error');
            }

            if (!number || number < 1) {
                hasError = true;
                $(this).find('.hs-chapter-number').addClass('hs-error');
                return;
            } else {
                $(this).find('.hs-chapter-number').removeClass('hs-error');
            }

            chapters.push({
                number: number,
                title: title
            });
        });

        if (hasError) {
            showMessage('Please fill in all chapter numbers and titles.', 'error');
            return;
        }

        if (chapters.length === 0) {
            showMessage('Please add at least one chapter.', 'error');
            return;
        }

        // Disable submit button
        $('#hs-submit-chapters-btn').prop('disabled', true).text('Submitting...');

        // Submit to API
        $.ajax({
            url: '/wp-json/gread/v1/books/' + bookId + '/chapters/submit',
            method: 'POST',
            beforeSend: function(xhr) {
                // Use WordPress REST API nonce
                if (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                }
            },
            contentType: 'application/json',
            data: JSON.stringify({
                chapters: chapters
            }),
            success: function(response) {
                if (response.success) {
                    showMessage(response.message || 'Chapters submitted successfully!', 'success');
                    setTimeout(function() {
                        closeChapterSubmissionModal();
                        location.reload(); // Reload to show pending status
                    }, 2000);
                } else {
                    showMessage(response.message || 'Failed to submit chapters.', 'error');
                    $('#hs-submit-chapters-btn').prop('disabled', false).text('Submit for Review');
                }
            },
            error: function(xhr) {
                let errorMsg = 'An error occurred. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showMessage(errorMsg, 'error');
                $('#hs-submit-chapters-btn').prop('disabled', false).text('Submit for Review');
            }
        });
    }

    /**
     * Show message in modal
     */
    function showMessage(message, type) {
        const messageClass = type === 'success' ? 'hs-success-message' : 'hs-error-message';
        $('#hs-chapters-message')
            .removeClass('hs-success-message hs-error-message')
            .addClass(messageClass)
            .html(message)
            .fadeIn();

        if (type === 'error') {
            setTimeout(function() {
                $('#hs-chapters-message').fadeOut();
            }, 5000);
        }
    }
});
