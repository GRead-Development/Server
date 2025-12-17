<?php

// Handles reporting, tracking, and resolving inaccuracies in the database.

/**
 * Get human-readable label for report type
 */
function hs_get_report_type_label($type) {
    $types = [
        'missing_page_count' => 'Missing Page Count',
        'incorrect_title' => 'Incorrect Title',
        'incorrect_author' => 'Incorrect Author',
        'incorrect_isbn' => 'Incorrect ISBN',
        'incorrect_cover' => 'Incorrect Cover',
        'incorrect_description' => 'Incorrect Description',
        'other' => 'Other'
    ];

    return isset($types[$type]) ? $types[$type] : 'Other';
}

// This creates the table to store reports about inaccuracies.
function hs_inaccuracies_create_table()
{
        global $wpdb;
        $table_name = $wpdb -> prefix . 'hs_book_reports';
        $charset_collate = $wpdb -> get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                book_id bigint(20) UNSIGNED NOT NULL,
                user_id bigint(20) UNSIGNED NOT NULL,
                report_type varchar(50) DEFAULT 'other' NOT NULL,
                report_text text NOT NULL,
                suggested_correction text NULL,
                status varchar(20) DEFAULT 'pending' NOT NULL,
                date_submitted datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                PRIMARY KEY (id),
                KEY book_id_index (book_id),
                KEY user_id_index (user_id),
                KEY status_index (status),
                KEY report_type_index (report_type)
        ) $charset_collate;";
        dbDelta($sql);
}



// Adds the Inaccuracies Manager
function hs_inaccuracies_add_admin_page()
{
        add_menu_page(
                'Inaccuracy Reports',
                'Inaccuracy Reports',
                'manage_options',
                'hs-inaccuracy-reports',
                'hs_inaccuracies_admin_page_html',
                'dashicons-flag',
                30
        );
}
add_action('admin_menu', 'hs_inaccuracies_add_admin_page');



function hs_inaccuracies_admin_page_html()
{
        global $wpdb;
        $table_name = $wpdb -> prefix . 'hs_book_reports';


        // Handle inaccuracies
        if (isset($_GET['action'], $_GET['report_id'], $_GET['_wpnonce']))
        {
                $report_id = intval($_GET['report_id']);
                if (wp_verify_nonce($_GET['_wpnonce'], 'hs_report_action_' . $report_id))
                {
                        $report = $wpdb -> get_row($wpdb -> prepare("SELECT * FROM $table_name WHERE id = %d", $report_id));
                        if ($report)
                        {
                                if ($_GET['action'] === 'approve')
                                {
                                        $wpdb -> update(
                                                $table_name,
                                                ['status' => 'approved'],
                                                ['id' => $report_id],
                                                ['%s'],
                                                ['%d']
                                        );
                                        if (function_exists('award_points'))
                                        {
                                                award_points($report -> user_id, 25);
                                        }

					hs_update_approved_reports_count($report -> user_id);
                                }
                                elseif ($_GET['action'] === 'reject')
                                {
                                        $wpdb -> update(
                                                $table_name,
                                                ['status' => 'rejected'],
                                                ['id' => $report_id],
                                                ['%s'],
                                                ['%d']
                                        );
                                }
                        }
                }
        }

        // Fixed: Add pagination to prevent memory spikes with many reports
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        $total_reports = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $total_pages = ceil($total_reports / $per_page);

        $reports = $wpdb -> get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY date_submitted DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        ?>

        <div class="wrap">
                <h1>Inaccuracy Reports</h1>
                <?php if ($total_reports > $per_page): ?>
                        <div class="tablenav top">
                                <div class="tablenav-pages">
                                        <span class="displaying-num"><?php echo esc_html($total_reports); ?> items</span>
                                        <?php
                                        echo paginate_links(array(
                                                'base' => add_query_arg('paged', '%#%'),
                                                'format' => '',
                                                'current' => $current_page,
                                                'total' => $total_pages,
                                                'prev_text' => '&laquo;',
                                                'next_text' => '&raquo;',
                                        ));
                                        ?>
                                </div>
                        </div>
                <?php endif; ?>

                <table class="wp-list-table widefat fixed striped">
                        <thead>
                                <tr>
                                        <th>Book Title</th>
                                        <th>Submitted By</th>
                                        <th>Type</th>
                                        <th>Details</th>
                                        <th>Suggested Fix</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                </tr>
                        </thead>

                        <tbody>
                                <?php if ($reports) : foreach ($reports as $report) :
                                        $report_type_label = hs_get_report_type_label($report->report_type ?? 'other');
                                ?>
                                        <tr>
                                                <td><a href="<?php echo esc_url(get_permalink($report -> book_id)); ?>" target="_blank"><?php echo esc_html(get_the_title($report -> book_id)); ?></a></td>
                                                <td><?php echo esc_html(get_userdata($report -> user_id) -> display_name); ?></td>
                                                <td><strong><?php echo esc_html($report_type_label); ?></strong></td>
                                                <td><?php echo esc_html($report -> report_text); ?></td>
                                                <td><?php echo !empty($report->suggested_correction) ? esc_html($report->suggested_correction) : '<em>None</em>'; ?></td>
                                                <td><?php echo esc_html($report -> date_submitted); ?></td>
                                                <td><span class="hs-report-status-<?php echo esc_attr($report -> status); ?>"><?php echo esc_html(ucfirst($report -> status)); ?></span></td>
                                                <td>
                                                        <?php if ($report -> status === 'pending') : ?>
                                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=hs-inaccuracy-reports&action=approve&report_id=' . $report -> id), 'hs_report_action_' . $report -> id)); ?>">Approve</a> |
                                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=hs-inaccuracy-reports&action=reject&report_id=' . $report -> id), 'hs_report_action_' . $report -> id)); ?>">Reject</a> |
                                                        <?php endif; ?>

                                                        <a href="<?php echo esc_url(get_edit_post_link($report -> book_id)); ?>" target="_blank">Edit Book</a>
                                                </td>
                                        </tr>
                                <?php endforeach; else : ?>
                                        <tr><td colspan="8">No reports found.</td></tr>
                                <?php endif; ?>
                        </tbody>
                </table>

                <?php if ($total_reports > $per_page): ?>
                        <div class="tablenav bottom">
                                <div class="tablenav-pages">
                                        <?php
                                        echo paginate_links(array(
                                                'base' => add_query_arg('paged', '%#%'),
                                                'format' => '',
                                                'current' => $current_page,
                                                'total' => $total_pages,
                                                'prev_text' => '&laquo;',
                                                'next_text' => '&raquo;',
                                        ));
                                        ?>
                                </div>
                        </div>
                <?php endif; ?>
        </div>
        <style>
                .hs-report-status-approved { color: green; font-weight: bold; }
                .hs-report-status-rejected { color: red; font-weight: bold; }
                .hs-report-status-pending { color: orange; font-weight: bold; }
        </style>
        <?php
}


function hs_ajax_submit_report()
{
    check_ajax_referer('hs_ajax_nonce', 'nonce');

    if (!is_user_logged_in() || !isset($_POST['book_id'], $_POST['report_text']))
    {
        wp_send_json_error(['message' => 'Invalid request.']);
    }

    global $wpdb;
    $table_name = $wpdb -> prefix . 'hs_book_reports';

    // Get report type from request, default to 'other'
    $report_type = isset($_POST['report_type']) ? sanitize_text_field($_POST['report_type']) : 'other';

    // Validate report type
    $valid_types = ['missing_page_count', 'incorrect_title', 'incorrect_author', 'incorrect_isbn', 'incorrect_cover', 'incorrect_description', 'other'];
    if (!in_array($report_type, $valid_types)) {
        $report_type = 'other';
    }

    // Data to be inserted
    $data_to_insert = [
        'book_id' => intval($_POST['book_id']),
        'user_id' => get_current_user_id(),
        'report_type' => $report_type,
        'report_text' => sanitize_textarea_field($_POST['report_text']),
        'suggested_correction' => isset($_POST['suggested_correction']) ? sanitize_textarea_field($_POST['suggested_correction']) : null,
        'date_submitted' => current_time('mysql'),
    ];

    $result = $wpdb -> insert(
        $table_name,
        $data_to_insert,
        ['%d', '%d', '%s', '%s', '%s', '%s']
    );

    if ($result === false) {
        error_log('DB Error in report submission: ' . $wpdb->last_error);
        error_log('Query: ' . $wpdb->last_query);
        wp_send_json_error([
            'message' => 'Could not submit report. Please try again.'
        ]);
	}


    if ($result)
    {
        wp_send_json_success(['message' => 'Report has been submitted. Thank you for making GRead even better!']);
    }
    else
    {
        // This is the original generic error, which we are now overriding for debug.
        wp_send_json_error(['message' => 'Could not submit report.']);
    }
}
add_action('wp_ajax_hs_submit_report', 'hs_ajax_submit_report');



function hs_add_report_modal_to_book_pages()
{
	if (!is_singular('book') || !is_user_logged_in())
	{
		return;
	}

	global $post;
	?>

	<div id="hs-report-modal" style="display:none;">
		<div class="hs-modal-content">
			<span id="hs-close-report-modal">&times;</span>
			<h2>Report an Inaccuracy</h2>
			<p>Is there something incorrect about this listing? Report it and get some points if you are correct!</p>

			<label for="hs-report-type"><strong>What's wrong?</strong></label>
			<select id="hs-report-type" style="width:100%; margin-bottom:10px; padding:8px;">
				<option value="incorrect_title">Incorrect Title</option>
				<option value="incorrect_author">Incorrect Author</option>
				<option value="missing_page_count">Missing Page Count</option>
				<option value="incorrect_isbn">Incorrect ISBN</option>
				<option value="incorrect_cover">Incorrect Cover Image</option>
				<option value="incorrect_description">Incorrect Description</option>
				<option value="other" selected>Other</option>
			</select>

			<label for="hs-report-textarea"><strong>Details</strong></label>
			<textarea id="hs-report-textarea" rows="4" placeholder="Describe the issue..." style="width:100%; margin-bottom:10px;"></textarea>

			<label for="hs-suggested-correction"><strong>Suggested Correction (Optional)</strong></label>
			<textarea id="hs-suggested-correction" rows="2" placeholder="What should it be?" style="width:100%; margin-bottom:10px;"></textarea>

			<button id="hs-submit-report-button" class="hs-button" data-book-id="<?php echo esc_attr($post -> ID); ?>">Submit Report</button>
			<div id="hs-report-feedback" style="margin-top:10px;"></div>
		</div>
	</div>
	<?php
}
add_action('wp_footer', 'hs_add_report_modal_to_book_pages');


function hs_update_approved_reports_count($user_id)
{
	if (!$user_id)
	{
		return;
	}

	global $wpdb;
	$reports_table = $wpdb -> prefix . 'hs_book_reports';

	$approved_count = $wpdb -> get_var($wpdb -> prepare(
		"SELECT COUNT(id) FROM $reports_table WHERE user_id = %d AND status = 'approved'",
		$user_id
	));

	update_user_meta($user_id, 'hs_approved_reports_count', (int)$approved_count);

	do_action('hs_stats_updated', $user_id);
}
