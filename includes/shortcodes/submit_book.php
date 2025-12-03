<?php
/**
 * Shortcode for users to submit books without ISBN
 * Usage: [submit_book]
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render book submission form
 */
function hs_submit_book_shortcode($atts = [], $content = null)
{
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to submit a book.</p>';
    }

    $user_id = get_current_user_id();

    ob_start();
    ?>

    <div class="submit-book-form-wrapper">
        <h2>Submit a Book Without ISBN</h2>
        <p>Can't find your book? Submit it here! If the book doesn't have an ISBN (like some older books, self-published books, or e-books), you can add it to our database. An admin will review it, but you can start tracking it in your library right away!</p>

        <div id="submit-book-message-area"></div>

        <form id="submit-book-form" method="post">
            <div class="form-group">
                <label for="book_title"><strong>Title *</strong></label>
                <input type="text" id="book_title" name="title" required class="widefat">
            </div>

            <div class="form-group">
                <label for="book_author"><strong>Author *</strong></label>
                <input type="text" id="book_author" name="author" required class="widefat">
                <small>Enter the author's full name (e.g., "J.K. Rowling")</small>
            </div>

            <div class="form-group">
                <label for="book_page_count"><strong>Page Count *</strong></label>
                <input type="number" id="book_page_count" name="page_count" min="1" required class="widefat">
            </div>

            <div class="form-group">
                <label for="book_description"><strong>Description</strong></label>
                <textarea id="book_description" name="description" rows="4" class="widefat"></textarea>
                <small>Optional: Brief description of the book</small>
            </div>

            <div class="form-group">
                <label for="book_cover_url"><strong>Cover Image URL</strong></label>
                <input type="url" id="book_cover_url" name="cover_url" class="widefat">
                <small>Optional: Direct link to the book cover image</small>
            </div>

            <div class="form-group">
                <label for="book_publication_year"><strong>Publication Year</strong></label>
                <input type="number" id="book_publication_year" name="publication_year" min="1000" max="<?php echo date('Y') + 1; ?>" class="widefat">
            </div>

            <div class="form-group">
                <label for="book_publisher"><strong>Publisher</strong></label>
                <input type="text" id="book_publisher" name="publisher" class="widefat">
            </div>

            <div class="form-group">
                <label for="book_external_id_type"><strong>External ID Type</strong></label>
                <select id="book_external_id_type" name="external_id_type" class="widefat">
                    <option value="">None</option>
                    <option value="ASIN">Amazon ASIN</option>
                    <option value="OCLC">OCLC Number</option>
                    <option value="LCCN">Library of Congress Control Number</option>
                    <option value="Goodreads">Goodreads ID</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group" id="external_id_group" style="display: none;">
                <label for="book_external_id"><strong>External ID</strong></label>
                <input type="text" id="book_external_id" name="external_id" class="widefat">
                <small id="external_id_help"></small>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="add_to_library" name="add_to_library" checked>
                    <strong>Add to my library after submission</strong>
                </label>
            </div>

            <p>
                <button type="submit" id="submit-book-btn" class="button button-primary">
                    Submit Book
                </button>
            </p>
        </form>
    </div>

    <style>
        .submit-book-form-wrapper {
            max-width: 600px;
            margin: 0 auto;
        }
        .submit-book-form-wrapper .form-group {
            margin-bottom: 20px;
        }
        .submit-book-form-wrapper label {
            display: block;
            margin-bottom: 5px;
        }
        .submit-book-form-wrapper input[type="text"],
        .submit-book-form-wrapper input[type="number"],
        .submit-book-form-wrapper input[type="url"],
        .submit-book-form-wrapper textarea,
        .submit-book-form-wrapper select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        .submit-book-form-wrapper small {
            color: #666;
            font-size: 0.9em;
        }
        .submit-book-form-wrapper .notice {
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid;
        }
        .submit-book-form-wrapper .notice-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .submit-book-form-wrapper .notice-error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .submit-book-form-wrapper .notice-info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
    </style>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var $form = $('#submit-book-form');
        var $messageArea = $('#submit-book-message-area');
        var $submitButton = $('#submit-book-btn');
        var $externalIdType = $('#book_external_id_type');
        var $externalIdGroup = $('#external_id_group');
        var $externalIdHelp = $('#external_id_help');

        // Show/hide external ID field based on type selection
        $externalIdType.on('change', function() {
            var type = $(this).val();
            if (type && type !== '') {
                $externalIdGroup.show();

                // Update help text based on type
                var helpText = {
                    'ASIN': 'Example: B08N5WRWNW (Amazon product ID)',
                    'OCLC': 'Example: 123456789 (WorldCat number)',
                    'LCCN': 'Example: 2020123456',
                    'Goodreads': 'Example: 12345678 (from the Goodreads URL)',
                    'Other': 'Enter the identifier'
                };
                $externalIdHelp.text(helpText[type] || '');
            } else {
                $externalIdGroup.hide();
            }
        });

        function displayMessage(type, message) {
            var messageHtml = '<div class="notice notice-' + type + '"><p>' + message + '</p></div>';
            $messageArea.html(messageHtml);

            // Scroll to message
            $('html, body').animate({
                scrollTop: $messageArea.offset().top - 100
            }, 500);
        }

        $form.on('submit', function(e) {
            e.preventDefault();

            $messageArea.empty();
            $submitButton.prop('disabled', true).text('Submitting...');

            var formData = {
                title: $('#book_title').val(),
                author: $('#book_author').val(),
                page_count: $('#book_page_count').val(),
                description: $('#book_description').val(),
                cover_url: $('#book_cover_url').val(),
                publication_year: $('#book_publication_year').val(),
                publisher: $('#book_publisher').val(),
                external_id_type: $('#book_external_id_type').val(),
                external_id: $('#book_external_id').val()
            };

            var addToLibrary = $('#add_to_library').is(':checked');

            // Submit the book
            $.ajax({
                type: 'POST',
                url: '<?php echo esc_url(rest_url('gread/v1/books/submit')); ?>',
                data: JSON.stringify(formData),
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                },
                success: function(response) {
                    if (response.success) {
                        var pendingBookId = response.data.id;
                        var message = response.message;

                        // If user wants to add to library, do that next
                        if (addToLibrary) {
                            $.ajax({
                                type: 'POST',
                                url: '<?php echo esc_url(rest_url('gread/v1/pending-books/')); ?>' + pendingBookId + '/add-to-library',
                                beforeSend: function(xhr) {
                                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                                },
                                success: function(libraryResponse) {
                                    if (libraryResponse.success) {
                                        message += ' The book has been added to your library!';
                                    }
                                    displayMessage('success', message);
                                    $form[0].reset();
                                },
                                error: function() {
                                    displayMessage('success', message + ' However, there was an issue adding it to your library. You can add it manually from your submissions page.');
                                    $form[0].reset();
                                }
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
                    $submitButton.prop('disabled', false).text('Submit Book');
                }
            });
        });
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('submit_book', 'hs_submit_book_shortcode');

/**
 * Shortcode to display user's pending book submissions
 * Usage: [my_pending_books]
 */
function hs_my_pending_books_shortcode($atts = [], $content = null)
{
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to view your submissions.</p>';
    }

    $user_id = get_current_user_id();
    $pending_books = hs_get_user_pending_books($user_id);

    ob_start();
    ?>

    <div class="my-pending-books">
        <h2>My Book Submissions</h2>

        <?php if (empty($pending_books)): ?>
            <p>You haven't submitted any books yet. <a href="<?php echo esc_url(home_url('/submit-book/')); ?>">Submit a book</a> to get started!</p>
        <?php else: ?>
            <table class="pending-books-list">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_books as $book): ?>
                        <tr class="status-<?php echo esc_attr($book->status); ?>">
                            <td><strong><?php echo esc_html($book->title); ?></strong></td>
                            <td><?php echo esc_html($book->author); ?></td>
                            <td>
                                <?php if ($book->status === 'pending'): ?>
                                    <span class="status-badge pending">⏳ Pending Review</span>
                                <?php elseif ($book->status === 'approved'): ?>
                                    <span class="status-badge approved">✓ Approved</span>
                                <?php elseif ($book->status === 'rejected'): ?>
                                    <span class="status-badge rejected">✗ Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(date('M j, Y', strtotime($book->submitted_at))); ?></td>
                            <td>
                                <?php if ($book->status === 'approved' && $book->approved_book_id): ?>
                                    <a href="<?php echo get_permalink($book->approved_book_id); ?>" class="button button-small">View Book</a>
                                <?php elseif ($book->status === 'rejected' && $book->rejection_reason): ?>
                                    <button class="button button-small view-reason" data-reason="<?php echo esc_attr($book->rejection_reason); ?>">View Reason</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <style>
        .my-pending-books {
            max-width: 900px;
            margin: 0 auto;
        }
        .pending-books-list {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .pending-books-list th,
        .pending-books-list td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .pending-books-list th {
            background: #f5f5f5;
            font-weight: bold;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.9em;
        }
        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-badge.approved {
            background: #d4edda;
            color: #155724;
        }
        .status-badge.rejected {
            background: #f8d7da;
            color: #721c24;
        }
    </style>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('.view-reason').on('click', function() {
            var reason = $(this).data('reason');
            alert('Rejection reason:\n\n' + reason);
        });
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('my_pending_books', 'hs_my_pending_books_shortcode');
