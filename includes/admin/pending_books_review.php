<?php
/**
 * Admin interface for reviewing pending book submissions
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register admin menu for pending books review
 */
function hs_register_pending_books_admin_menu()
{
    add_submenu_page(
        'edit.php?post_type=book',
        'Pending Books',
        'Pending Books',
        'manage_options',
        'pending-books',
        'hs_render_pending_books_admin_page'
    );
}
add_action('admin_menu', 'hs_register_pending_books_admin_menu');

/**
 * Render the pending books admin page
 */
function hs_render_pending_books_admin_page()
{
    // Get pending count for badge
    $pending_count = hs_get_pending_books_count('pending');

    ?>
    <div class="wrap">
        <h1>Pending Book Submissions
            <?php if ($pending_count > 0): ?>
                <span class="update-plugins count-<?php echo $pending_count; ?>">
                    <span class="update-count"><?php echo $pending_count; ?></span>
                </span>
            <?php endif; ?>
        </h1>

        <div class="pending-books-tabs">
            <h2 class="nav-tab-wrapper">
                <a href="#pending" class="nav-tab nav-tab-active" data-tab="pending">
                    Pending Review (<?php echo $pending_count; ?>)
                </a>
                <a href="#approved" class="nav-tab" data-tab="approved">
                    Approved (<?php echo hs_get_pending_books_count('approved'); ?>)
                </a>
                <a href="#rejected" class="nav-tab" data-tab="rejected">
                    Rejected (<?php echo hs_get_pending_books_count('rejected'); ?>)
                </a>
            </h2>
        </div>

        <div id="pending-tab" class="tab-content active">
            <?php hs_render_pending_books_table('pending'); ?>
        </div>

        <div id="approved-tab" class="tab-content" style="display: none;">
            <?php hs_render_pending_books_table('approved'); ?>
        </div>

        <div id="rejected-tab" class="tab-content" style="display: none;">
            <?php hs_render_pending_books_table('rejected'); ?>
        </div>
    </div>

    <style>
        .pending-books-table {
            width: 100%;
            margin-top: 20px;
        }
        .pending-books-table th {
            text-align: left;
            padding: 10px;
            background: #f0f0f0;
        }
        .pending-books-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .book-cover-thumb {
            max-width: 60px;
            max-height: 90px;
        }
        .book-details {
            margin: 5px 0;
        }
        .book-meta {
            color: #666;
            font-size: 0.9em;
        }
        .action-buttons button {
            margin-right: 5px;
        }
        .rejection-reason {
            background: #fff3cd;
            padding: 10px;
            margin-top: 5px;
            border-left: 3px solid #ffc107;
        }
        .tab-content {
            padding: 20px 0;
        }
        .nav-tab-wrapper {
            margin-bottom: 20px;
        }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Tab switching
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');

            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.tab-content').hide();
            $('#' + tab + '-tab').show();
        });

        // Approve book
        $(document).on('click', '.approve-book', function() {
            var bookId = $(this).data('book-id');
            var row = $(this).closest('tr');

            if (!confirm('Are you sure you want to approve this book and make it publicly available?')) {
                return;
            }

            $(this).prop('disabled', true).text('Approving...');

            $.post(ajaxurl, {
                action: 'hs_approve_pending_book',
                book_id: bookId,
                nonce: '<?php echo wp_create_nonce('approve_pending_book'); ?>'
            }, function(response) {
                if (response.success) {
                    row.fadeOut(400, function() {
                        row.remove();
                        location.reload();
                    });
                } else {
                    alert('Error: ' + response.data.message);
                    location.reload();
                }
            });
        });

        // Reject book
        $(document).on('click', '.reject-book', function() {
            var bookId = $(this).data('book-id');
            var row = $(this).closest('tr');

            var reason = prompt('Please provide a reason for rejection (optional):');
            if (reason === null) return; // User cancelled

            $(this).prop('disabled', true).text('Rejecting...');

            $.post(ajaxurl, {
                action: 'hs_reject_pending_book',
                book_id: bookId,
                reason: reason,
                nonce: '<?php echo wp_create_nonce('reject_pending_book'); ?>'
            }, function(response) {
                if (response.success) {
                    row.fadeOut(400, function() {
                        row.remove();
                        location.reload();
                    });
                } else {
                    alert('Error: ' + response.data.message);
                    location.reload();
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Render pending books table by status
 */
function hs_render_pending_books_table($status)
{
    $books = hs_get_pending_books($status, 100, 0);

    if (empty($books)) {
        echo '<p>No books found with status: ' . esc_html($status) . '</p>';
        return;
    }

    ?>
    <table class="pending-books-table widefat">
        <thead>
            <tr>
                <th>Cover</th>
                <th>Book Details</th>
                <th>Submitted By</th>
                <th>Date</th>
                <?php if ($status === 'pending'): ?>
                    <th>Actions</th>
                <?php elseif ($status === 'approved'): ?>
                    <th>Book ID</th>
                <?php elseif ($status === 'rejected'): ?>
                    <th>Reason</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($books as $book): ?>
                <tr>
                    <td>
                        <?php if ($book->cover_url): ?>
                            <img src="<?php echo esc_url($book->cover_url); ?>"
                                 alt="Cover" class="book-cover-thumb">
                        <?php else: ?>
                            <div style="width: 60px; height: 90px; background: #ddd; display: flex; align-items: center; justify-content: center;">
                                No cover
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="book-details">
                            <strong><?php echo esc_html($book->title); ?></strong>
                        </div>
                        <div class="book-meta">
                            by <?php echo esc_html($book->author); ?>
                            <br>
                            <?php echo intval($book->page_count); ?> pages
                            <?php if ($book->publication_year): ?>
                                | Published: <?php echo intval($book->publication_year); ?>
                            <?php endif; ?>
                            <?php if ($book->external_id): ?>
                                <br>
                                <?php echo esc_html($book->external_id_type); ?>:
                                <?php echo esc_html($book->external_id); ?>
                            <?php endif; ?>
                            <br>
                            <small>GID: <?php echo esc_html($book->gid); ?></small>
                        </div>
                        <?php if ($book->description): ?>
                            <div class="book-meta" style="margin-top: 5px;">
                                <?php echo wp_kses_post(wp_trim_words($book->description, 30)); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo esc_html($book->submitter_name ?? 'Unknown'); ?>
                        <br>
                        <small>ID: <?php echo intval($book->user_id); ?></small>
                    </td>
                    <td>
                        <?php echo esc_html(date('Y-m-d H:i', strtotime($book->submitted_at))); ?>
                        <?php if ($book->reviewed_at): ?>
                            <br>
                            <small>Reviewed: <?php echo esc_html(date('Y-m-d H:i', strtotime($book->reviewed_at))); ?></small>
                        <?php endif; ?>
                    </td>
                    <?php if ($status === 'pending'): ?>
                        <td class="action-buttons">
                            <button type="button"
                                    class="button button-primary approve-book"
                                    data-book-id="<?php echo intval($book->id); ?>">
                                Approve
                            </button>
                            <button type="button"
                                    class="button reject-book"
                                    data-book-id="<?php echo intval($book->id); ?>">
                                Reject
                            </button>
                        </td>
                    <?php elseif ($status === 'approved'): ?>
                        <td>
                            <?php if ($book->approved_book_id): ?>
                                <a href="<?php echo get_edit_post_link($book->approved_book_id); ?>" target="_blank">
                                    Edit Book #<?php echo intval($book->approved_book_id); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    <?php elseif ($status === 'rejected'): ?>
                        <td>
                            <?php if ($book->rejection_reason): ?>
                                <div class="rejection-reason">
                                    <?php echo esc_html($book->rejection_reason); ?>
                                </div>
                            <?php else: ?>
                                <em>No reason provided</em>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/**
 * AJAX handler for approving pending book
 */
function hs_ajax_approve_pending_book()
{
    check_ajax_referer('approve_pending_book', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $book_id = intval($_POST['book_id']);
    $admin_user_id = get_current_user_id();

    $result = hs_approve_pending_book($book_id, $admin_user_id);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_hs_approve_pending_book', 'hs_ajax_approve_pending_book');

/**
 * AJAX handler for rejecting pending book
 */
function hs_ajax_reject_pending_book()
{
    check_ajax_referer('reject_pending_book', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $book_id = intval($_POST['book_id']);
    $reason = sanitize_textarea_field($_POST['reason'] ?? '');
    $admin_user_id = get_current_user_id();

    $result = hs_reject_pending_book($book_id, $admin_user_id, $reason);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_hs_reject_pending_book', 'hs_ajax_reject_pending_book');

/**
 * Add notification badge to Books menu
 */
function hs_add_pending_books_badge_to_menu()
{
    global $menu, $submenu;

    $pending_count = hs_get_pending_books_count('pending');

    if ($pending_count > 0) {
        // Find the Books menu item
        foreach ($menu as $key => $item) {
            if ($item[2] === 'edit.php?post_type=book') {
                $menu[$key][0] .= ' <span class="update-plugins count-' . $pending_count . '"><span class="update-count">' . number_format_i18n($pending_count) . '</span></span>';
                break;
            }
        }
    }
}
add_action('admin_menu', 'hs_add_pending_books_badge_to_menu', 999);
