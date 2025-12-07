<?php
/**
 * Comprehensive admin review page for all user contributions
 * Simplified single-file version
 */

if (!defined('ABSPATH')) exit;

// Register menu
add_action('admin_menu', function() {
    add_submenu_page('hotsoup-admin', 'Contributions', 'Contributions', 'manage_options', 'all-contributions', 'hs_all_contributions_page');
});

// AJAX Handlers
add_action('wp_ajax_hs_approve_char', function() {
    check_ajax_referer('approve_char', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $r = hs_approve_character_submission(intval($_POST['id']), get_current_user_id());
    wp_send_json($r);
});

add_action('wp_ajax_hs_reject_char', function() {
    check_ajax_referer('reject_char', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $r = hs_reject_character_submission(intval($_POST['id']), get_current_user_id(), $_POST['reason'] ?? '');
    wp_send_json($r);
});

add_action('wp_ajax_hs_approve_tag', function() {
    check_ajax_referer('approve_tag', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $r = hs_approve_tag_suggestion(intval($_POST['id']), get_current_user_id());
    wp_send_json($r);
});

add_action('wp_ajax_hs_reject_tag', function() {
    check_ajax_referer('reject_tag', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $r = hs_reject_tag_suggestion(intval($_POST['id']), get_current_user_id(), $_POST['reason'] ?? '');
    wp_send_json($r);
});

add_action('wp_ajax_hs_approve_sum', function() {
    check_ajax_referer('approve_sum', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $r = hs_approve_chapter_summary(intval($_POST['id']), get_current_user_id());
    wp_send_json($r);
});

add_action('wp_ajax_hs_reject_sum', function() {
    check_ajax_referer('reject_sum', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $r = hs_reject_chapter_summary(intval($_POST['id']), get_current_user_id(), $_POST['reason'] ?? '');
    wp_send_json($r);
});

// Bulk approval handlers
add_action('wp_ajax_hs_bulk_approve_char', function() {
    check_ajax_referer('bulk_approve', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
    $admin_id = get_current_user_id();
    $success = 0;
    $failed = 0;
    foreach ($ids as $id) {
        $r = hs_approve_character_submission($id, $admin_id);
        if ($r['success']) $success++; else $failed++;
    }
    wp_send_json(['success' => true, 'approved' => $success, 'failed' => $failed]);
});

add_action('wp_ajax_hs_bulk_approve_tag', function() {
    check_ajax_referer('bulk_approve', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
    $admin_id = get_current_user_id();
    $success = 0;
    $failed = 0;
    foreach ($ids as $id) {
        $r = hs_approve_tag_suggestion($id, $admin_id);
        if ($r['success']) $success++; else $failed++;
    }
    wp_send_json(['success' => true, 'approved' => $success, 'failed' => $failed]);
});

add_action('wp_ajax_hs_bulk_approve_sum', function() {
    check_ajax_referer('bulk_approve', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
    $admin_id = get_current_user_id();
    $success = 0;
    $failed = 0;
    foreach ($ids as $id) {
        $r = hs_approve_chapter_summary($id, $admin_id);
        if ($r['success']) $success++; else $failed++;
    }
    wp_send_json(['success' => true, 'approved' => $success, 'failed' => $failed]);
});

// Main admin page
function hs_all_contributions_page() {
    global $wpdb;

    // Get counts
    $ch_pend = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_character_submissions WHERE status='pending'");
    $tg_pend = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_tag_suggestions WHERE status='pending'");
    $sm_pend = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_chapter_summaries WHERE status='pending'");
    $total = $ch_pend + $tg_pend + $sm_pend;

    ?>
    <div class="wrap">
        <h1>User Contributions <?php if($total>0) echo '<span class="update-plugins count-'.$total.'"><span class="update-count">'.$total.'</span></span>'; ?></h1>
        <p>Review user-submitted characters (15pts), tags (3pts), and chapter summaries (25pts)</p>

        <h2 class="nav-tab-wrapper">
            <a href="#chars" class="nav-tab nav-tab-active" data-tab="chars">Characters (<?php echo $ch_pend; ?>)</a>
            <a href="#tags" class="nav-tab" data-tab="tags">Tags (<?php echo $tg_pend; ?>)</a>
            <a href="#sums" class="nav-tab" data-tab="sums">Summaries (<?php echo $sm_pend; ?>)</a>
        </h2>

        <div id="chars-content" class="tab-content">
            <div class="bulk-actions-bar">
                <button class="button button-primary bulk-approve-btn" data-type="char" disabled>Approve Selected</button>
                <span class="selection-count"></span>
            </div>
            <?php hs_render_char_table('pending'); ?>
        </div>
        <div id="tags-content" class="tab-content" style="display:none;">
            <div class="bulk-actions-bar">
                <button class="button button-primary bulk-approve-btn" data-type="tag" disabled>Approve Selected</button>
                <span class="selection-count"></span>
            </div>
            <?php hs_render_tag_table('pending'); ?>
        </div>
        <div id="sums-content" class="tab-content" style="display:none;">
            <div class="bulk-actions-bar">
                <button class="button button-primary bulk-approve-btn" data-type="sum" disabled>Approve Selected</button>
                <span class="selection-count"></span>
            </div>
            <?php hs_render_sum_table('pending'); ?>
        </div>
    </div>

    <style>
        .tab-content { padding: 20px 0; }
        .bulk-actions-bar { margin-bottom: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; }
        .bulk-actions-bar .selection-count { margin-left: 10px; color: #666; }
        .contrib-table { width: 100%; margin-top: 10px; }
        .contrib-table th { background: #f0f0f0; padding: 10px; text-align: left; }
        .contrib-table td { padding: 10px; border-bottom: 1px solid #ddd; }
        .contrib-table input[type="checkbox"] { margin: 0; }
        .char-list, .sum-text { background: #f9f9f9; padding: 10px; border-radius: 3px; max-height: 150px; overflow-y: auto; }
    </style>

    <script>
    jQuery(document).ready(function($) {
        var nonces = {
            approve_char: '<?php echo wp_create_nonce("approve_char"); ?>',
            reject_char: '<?php echo wp_create_nonce("reject_char"); ?>',
            approve_tag: '<?php echo wp_create_nonce("approve_tag"); ?>',
            reject_tag: '<?php echo wp_create_nonce("reject_tag"); ?>',
            approve_sum: '<?php echo wp_create_nonce("approve_sum"); ?>',
            reject_sum: '<?php echo wp_create_nonce("reject_sum"); ?>',
            bulk_approve: '<?php echo wp_create_nonce("bulk_approve"); ?>'
        };

        $('.nav-tab').click(function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('.tab-content').hide();
            $('#' + tab + '-content').show();
            updateBulkButtons();
        });

        // Select all checkbox
        $(document).on('change', '.select-all', function() {
            var $table = $(this).closest('table');
            $table.find('.item-checkbox').prop('checked', this.checked);
            updateBulkButtons();
        });

        // Individual checkbox
        $(document).on('change', '.item-checkbox', function() {
            updateBulkButtons();
            var $table = $(this).closest('table');
            var allChecked = $table.find('.item-checkbox:checked').length === $table.find('.item-checkbox').length;
            $table.find('.select-all').prop('checked', allChecked);
        });

        function updateBulkButtons() {
            $('.tab-content:visible').each(function() {
                var $tab = $(this);
                var $checked = $tab.find('.item-checkbox:checked');
                var count = $checked.length;
                var $btn = $tab.find('.bulk-approve-btn');
                var $count = $tab.find('.selection-count');

                if (count > 0) {
                    $btn.prop('disabled', false);
                    $count.text(count + ' selected');
                } else {
                    $btn.prop('disabled', true);
                    $count.text('');
                }
            });
        }

        // Bulk approve
        $('.bulk-approve-btn').click(function() {
            var type = $(this).data('type');
            var $tab = $(this).closest('.tab-content');
            var ids = [];
            $tab.find('.item-checkbox:checked').each(function() {
                ids.push($(this).val());
            });

            if (ids.length === 0) return;
            if (!confirm('Approve ' + ids.length + ' items?')) return;

            $(this).prop('disabled', true).text('Approving...');

            $.post(ajaxurl, {
                action: 'hs_bulk_approve_' + type,
                ids: ids,
                nonce: nonces.bulk_approve
            }, function(r) {
                if (r.success) {
                    alert('Approved ' + r.approved + ' items' + (r.failed > 0 ? ', ' + r.failed + ' failed' : ''));
                    location.reload();
                } else {
                    alert('Error: ' + (r.message || 'Unknown'));
                    location.reload();
                }
            });
        });

        function doAction($btn, action, nonceKey) {
            var id = $btn.data('id');
            var reason = action.includes('reject') ? prompt('Reason (optional):') : null;
            if (action.includes('reject') && reason === null) return;

            $btn.prop('disabled', true);
            $.post(ajaxurl, {
                action: action,
                id: id,
                reason: reason || '',
                nonce: nonces[nonceKey]
            }, function(r) {
                if (r.success) {
                    $btn.closest('tr').fadeOut();
                    setTimeout(() => location.reload(), 500);
                } else {
                    alert('Error: ' + (r.message || 'Unknown'));
                    location.reload();
                }
            });
        }

        $('.approve-char').click(function() { if(confirm('Approve? +15 pts')) doAction($(this), 'hs_approve_char', 'approve_char'); });
        $('.reject-char').click(function() { doAction($(this), 'hs_reject_char', 'reject_char'); });
        $('.approve-tag').click(function() { if(confirm('Approve? +3 pts')) doAction($(this), 'hs_approve_tag', 'approve_tag'); });
        $('.reject-tag').click(function() { doAction($(this), 'hs_reject_tag', 'reject_tag'); });
        $('.approve-sum').click(function() { if(confirm('Approve? +25 pts')) doAction($(this), 'hs_approve_sum', 'approve_sum'); });
        $('.reject-sum').click(function() { doAction($(this), 'hs_reject_sum', 'reject_sum'); });
    });
    </script>
    <?php
}

function hs_render_char_table($status) {
    global $wpdb;
    $subs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hs_character_submissions WHERE status = %s ORDER BY submitted_at DESC LIMIT 50",
        $status
    ));

    if (empty($subs)) { echo '<p>No character submissions.</p>'; return; }

    echo '<table class="contrib-table widefat"><thead><tr>
        <th><input type="checkbox" class="select-all"></th>
        <th>Book</th><th>Characters</th><th>Submitted By</th><th>Date</th><th>Actions</th>
    </tr></thead><tbody>';

    foreach ($subs as $s) {
        $chars = json_decode($s->characters_data, true) ?: [];
        $book = get_post($s->book_id);
        $user = get_userdata($s->user_id);

        echo '<tr>';
        echo '<td><input type="checkbox" class="item-checkbox" value="'.$s->id.'"></td>';
        echo '<td><strong>'.esc_html($book ? $book->post_title : 'Unknown').'</strong></td>';
        echo '<td><div class="char-list">';
        foreach ($chars as $c) {
            echo '<div>• '.esc_html($c['name']);
            if (!empty($c['description'])) echo ' - <em>'.esc_html($c['description']).'</em>';
            echo '</div>';
        }
        echo '</div></td>';
        echo '<td>'.esc_html($user ? $user->display_name : 'Unknown').'</td>';
        echo '<td>'.esc_html(date('Y-m-d H:i', strtotime($s->submitted_at))).'</td>';
        echo '<td>
            <button class="button button-primary approve-char" data-id="'.$s->id.'">Approve (+15)</button>
            <button class="button reject-char" data-id="'.$s->id.'">Reject</button>
        </td></tr>';
    }

    echo '</tbody></table>';
}

function hs_render_tag_table($status) {
    global $wpdb;
    $subs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hs_tag_suggestions WHERE status = %s ORDER BY submitted_at DESC LIMIT 50",
        $status
    ));

    if (empty($subs)) { echo '<p>No tag suggestions.</p>'; return; }

    echo '<table class="contrib-table widefat"><thead><tr>
        <th><input type="checkbox" class="select-all"></th>
        <th>Book</th><th>Tag</th><th>Submitted By</th><th>Date</th><th>Actions</th>
    </tr></thead><tbody>';

    foreach ($subs as $s) {
        $book = get_post($s->book_id);
        $user = get_userdata($s->user_id);

        echo '<tr>';
        echo '<td><input type="checkbox" class="item-checkbox" value="'.$s->id.'"></td>';
        echo '<td><strong>'.esc_html($book ? $book->post_title : 'Unknown').'</strong></td>';
        echo '<td><span class="tag">'.esc_html($s->tag_name).'</span></td>';
        echo '<td>'.esc_html($user ? $user->display_name : 'Unknown').'</td>';
        echo '<td>'.esc_html(date('Y-m-d H:i', strtotime($s->submitted_at))).'</td>';
        echo '<td>
            <button class="button button-primary approve-tag" data-id="'.$s->id.'">Approve (+3)</button>
            <button class="button reject-tag" data-id="'.$s->id.'">Reject</button>
        </td></tr>';
    }

    echo '</tbody></table>';
}

function hs_render_sum_table($status) {
    global $wpdb;
    $subs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hs_chapter_summaries WHERE status = %s ORDER BY submitted_at DESC LIMIT 50",
        $status
    ));

    if (empty($subs)) { echo '<p>No chapter summaries.</p>'; return; }

    echo '<table class="contrib-table widefat"><thead><tr>
        <th><input type="checkbox" class="select-all"></th>
        <th>Book</th><th>Chapter</th><th>Summary</th><th>Submitted By</th><th>Date</th><th>Actions</th>
    </tr></thead><tbody>';

    foreach ($subs as $s) {
        $book = get_post($s->book_id);
        $user = get_userdata($s->user_id);

        echo '<tr>';
        echo '<td><input type="checkbox" class="item-checkbox" value="'.$s->id.'"></td>';
        echo '<td><strong>'.esc_html($book ? $book->post_title : 'Unknown').'</strong></td>';
        echo '<td>Chapter '.$s->chapter_number;
        if ($s->chapter_title) echo '<br><em>'.esc_html($s->chapter_title).'</em>';
        echo '</td>';
        echo '<td><div class="sum-text">'.esc_html($s->summary).'</div></td>';
        echo '<td>'.esc_html($user ? $user->display_name : 'Unknown').'</td>';
        echo '<td>'.esc_html(date('Y-m-d H:i', strtotime($s->submitted_at))).'</td>';
        echo '<td>
            <button class="button button-primary approve-sum" data-id="'.$s->id.'">Approve (+25)</button>
            <button class="button reject-sum" data-id="'.$s->id.'">Reject</button>
        </td></tr>';
    }

    echo '</tbody></table>';
}
