<?php
/**
 * Admin interface for reviewing chapter submissions
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register admin menu for chapter submissions review
 */
function hs_register_chapter_submissions_admin_menu()
{
    add_submenu_page(
        'hotsoup-admin',
        'Chapter Submissions',
        'Chapter Submissions',
        'manage_options',
        'chapter-submissions',
        'hs_render_chapter_submissions_admin_page'
    );
}
add_action('admin_menu', 'hs_register_chapter_submissions_admin_menu');

/**
 * Get chapter submissions count by status
 */
function hs_get_chapter_submissions_count($status = 'pending') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_submissions';

    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
        $status
    ));
}

/**
 * Get chapter submissions by status
 */
function hs_get_chapter_submissions_admin($status, $limit = 100, $offset = 0) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_submissions';

    $submissions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE status = %s ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
        $status,
        $limit,
        $offset
    ));

    // Add book and user info
    foreach ($submissions as $submission) {
        // Decode chapters
        if (!empty($submission->chapters_data)) {
            $submission->chapters = json_decode($submission->chapters_data, true);
        }

        // Get book info
        $book = get_post($submission->book_id);
        $submission->book_title = $book ? $book->post_title : 'Unknown Book';
        $submission->book_author = $book ? get_post_meta($book->ID, 'book_author', true) : '';

        // Get submitter info
        $user = get_userdata($submission->user_id);
        $submission->submitter_name = $user ? $user->display_name : 'Unknown User';

        // Get reviewer info if reviewed
        if ($submission->reviewed_by) {
            $reviewer = get_userdata($submission->reviewed_by);
            $submission->reviewer_name = $reviewer ? $reviewer->display_name : 'Unknown';
        }
    }

    return $submissions;
}

/**
 * Render the chapter submissions admin page
 */
function hs_render_chapter_submissions_admin_page()
{
    // Get pending count for badge
    $pending_count = hs_get_chapter_submissions_count('pending');

    ?>
    <div class="wrap">
        <h1>Chapter Submissions
            <?php if ($pending_count > 0): ?>
                <span class="update-plugins count-<?php echo $pending_count; ?>">
                    <span class="update-count"><?php echo $pending_count; ?></span>
                </span>
            <?php endif; ?>
        </h1>

        <p>Users can submit chapter information for books. Each approved submission awards the user 10 points.</p>

        <div class="chapter-submissions-tabs">
            <h2 class="nav-tab-wrapper">
                <a href="#pending" class="nav-tab nav-tab-active" data-tab="pending">
                    Pending Review (<?php echo $pending_count; ?>)
                </a>
                <a href="#approved" class="nav-tab" data-tab="approved">
                    Approved (<?php echo hs_get_chapter_submissions_count('approved'); ?>)
                </a>
                <a href="#rejected" class="nav-tab" data-tab="rejected">
                    Rejected (<?php echo hs_get_chapter_submissions_count('rejected'); ?>)
                </a>
            </h2>
        </div>

        <div id="pending-tab" class="tab-content active">
            <?php hs_render_chapter_submissions_table('pending'); ?>
        </div>

        <div id="approved-tab" class="tab-content" style="display: none;">
            <?php hs_render_chapter_submissions_table('approved'); ?>
        </div>

        <div id="rejected-tab" class="tab-content" style="display: none;">
            <?php hs_render_chapter_submissions_table('rejected'); ?>
        </div>
    </div>

    <style>
        .chapter-submissions-table {
            width: 100%;
            margin-top: 20px;
        }
        .chapter-submissions-table th {
            text-align: left;
            padding: 10px;
            background: #f0f0f0;
        }
        .chapter-submissions-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        .book-details {
            margin: 5px 0;
        }
        .book-meta {
            color: #666;
            font-size: 0.9em;
        }
        .chapters-list {
            max-height: 200px;
            overflow-y: auto;
            background: #f9f9f9;
            padding: 10px;
            border-radius: 3px;
            margin-top: 5px;
        }
        .chapter-item {
            padding: 3px 0;
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
        .submission-id {
            color: #999;
            font-size: 0.85em;
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

        // Approve submission
        $(document).on('click', '.approve-submission', function() {
            var submissionId = $(this).data('submission-id');
            var row = $(this).closest('tr');

            if (!confirm('Are you sure you want to approve this chapter submission? The user will receive 10 points.')) {
                return;
            }

            $(this).prop('disabled', true).text('Approving...');

            $.post(ajaxurl, {
                action: 'hs_approve_chapter_submission',
                submission_id: submissionId,
                nonce: '<?php echo wp_create_nonce('approve_chapter_submission'); ?>'
            }, function(response) {
                if (response.success) {
                    row.fadeOut(400, function() {
                        row.remove();
                        location.reload();
                    });
                } else {
                    alert('Error: ' + (response.data ? response.data.message : 'Unknown error'));
                    location.reload();
                }
            });
        });

        // Reject submission
        $(document).on('click', '.reject-submission', function() {
            var submissionId = $(this).data('submission-id');
            var row = $(this).closest('tr');

            var reason = prompt('Please provide a reason for rejection (optional):');
            if (reason === null) return; // User cancelled

            $(this).prop('disabled', true).text('Rejecting...');

            $.post(ajaxurl, {
                action: 'hs_reject_chapter_submission',
                submission_id: submissionId,
                reason: reason,
                nonce: '<?php echo wp_create_nonce('reject_chapter_submission'); ?>'
            }, function(response) {
                if (response.success) {
                    row.fadeOut(400, function() {
                        row.remove();
                        location.reload();
                    });
                } else {
                    alert('Error: ' + (response.data ? response.data.message : 'Unknown error'));
                    location.reload();
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Render chapter submissions table by status
 */
function hs_render_chapter_submissions_table($status)
{
    $submissions = hs_get_chapter_submissions_admin($status, 100, 0);

    if (empty($submissions)) {
        echo '<p>No chapter submissions found with status: ' . esc_html($status) . '</p>';
        return;
    }

    ?>
    <table class="chapter-submissions-table widefat">
        <thead>
            <tr>
                <th>Book</th>
                <th>Chapters</th>
                <th>Submitted By</th>
                <th>Date</th>
                <?php if ($status === 'pending'): ?>
                    <th>Actions</th>
                <?php elseif ($status === 'approved'): ?>
                    <th>Reviewed By</th>
                <?php elseif ($status === 'rejected'): ?>
                    <th>Reason</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($submissions as $submission): ?>
                <tr>
                    <td>
                        <div class="book-details">
                            <strong><?php echo esc_html($submission->book_title); ?></strong>
                        </div>
                        <div class="book-meta">
                            <?php if ($submission->book_author): ?>
                                by <?php echo esc_html($submission->book_author); ?>
                                <br>
                            <?php endif; ?>
                            <a href="<?php echo get_edit_post_link($submission->book_id); ?>" target="_blank">
                                Edit Book #<?php echo intval($submission->book_id); ?>
                            </a>
                            <br>
                            <span class="submission-id">Submission #<?php echo intval($submission->id); ?></span>
                        </div>
                    </td>
                    <td>
                        <?php if (!empty($submission->chapters)): ?>
                            <div class="chapters-list">
                                <strong><?php echo count($submission->chapters); ?> chapters:</strong>
                                <?php foreach ($submission->chapters as $chapter): ?>
                                    <div class="chapter-item">
                                        <strong>Chapter <?php echo intval($chapter['number']); ?>:</strong>
                                        <?php echo esc_html($chapter['title']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <em>No chapters data</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo esc_html($submission->submitter_name); ?>
                        <br>
                        <small>User ID: <?php echo intval($submission->user_id); ?></small>
                    </td>
                    <td>
                        <?php echo esc_html(date('Y-m-d H:i', strtotime($submission->submitted_at))); ?>
                        <?php if ($submission->reviewed_at): ?>
                            <br>
                            <small>Reviewed: <?php echo esc_html(date('Y-m-d H:i', strtotime($submission->reviewed_at))); ?></small>
                        <?php endif; ?>
                    </td>
                    <?php if ($status === 'pending'): ?>
                        <td class="action-buttons">
                            <button type="button"
                                    class="button button-primary approve-submission"
                                    data-submission-id="<?php echo intval($submission->id); ?>">
                                Approve (+10 pts)
                            </button>
                            <button type="button"
                                    class="button reject-submission"
                                    data-submission-id="<?php echo intval($submission->id); ?>">
                                Reject
                            </button>
                        </td>
                    <?php elseif ($status === 'approved'): ?>
                        <td>
                            <?php echo esc_html($submission->reviewer_name ?? 'Unknown'); ?>
                        </td>
                    <?php elseif ($status === 'rejected'): ?>
                        <td>
                            <?php if ($submission->rejection_reason): ?>
                                <div class="rejection-reason">
                                    <?php echo esc_html($submission->rejection_reason); ?>
                                </div>
                            <?php else: ?>
                                <em>No reason provided</em>
                            <?php endif; ?>
                            <?php if ($submission->reviewer_name): ?>
                                <br><small>by <?php echo esc_html($submission->reviewer_name); ?></small>
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
 * AJAX handler for approving chapter submission
 */
function hs_ajax_approve_chapter_submission()
{
    check_ajax_referer('approve_chapter_submission', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $submission_id = intval($_POST['submission_id']);
    $admin_user_id = get_current_user_id();

    $result = hs_approve_chapter_submission($submission_id, $admin_user_id);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_hs_approve_chapter_submission', 'hs_ajax_approve_chapter_submission');

/**
 * AJAX handler for rejecting chapter submission
 */
function hs_ajax_reject_chapter_submission()
{
    check_ajax_referer('reject_chapter_submission', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $submission_id = intval($_POST['submission_id']);
    $reason = sanitize_textarea_field($_POST['reason'] ?? '');
    $admin_user_id = get_current_user_id();

    $result = hs_reject_chapter_submission($submission_id, $admin_user_id, $reason);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_hs_reject_chapter_submission', 'hs_ajax_reject_chapter_submission');

/**
 * Add notification badge to HotSoup menu for pending chapter submissions
 */
function hs_add_chapter_submissions_badge_to_menu()
{
    global $submenu;

    $pending_count = hs_get_chapter_submissions_count('pending');

    if ($pending_count > 0 && isset($submenu['hotsoup-admin'])) {
        foreach ($submenu['hotsoup-admin'] as $key => $item) {
            if ($item[2] === 'chapter-submissions') {
                $submenu['hotsoup-admin'][$key][0] .= ' <span class="update-plugins count-' . $pending_count . '"><span class="update-count">' . number_format_i18n($pending_count) . '</span></span>';
                break;
            }
        }
    }
}
add_action('admin_menu', 'hs_add_chapter_submissions_badge_to_menu', 999);
