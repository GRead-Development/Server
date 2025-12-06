<?php
/**
 * Unified Admin Interface for Reviewing All User Contributions
 * Includes: Chapters, Characters, Tags, and Chapter Summaries
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register admin menu for contributions review
 */
function hs_register_contributions_admin_menu() {
    add_submenu_page(
        'hotsoup-admin',
        'User Contributions',
        'User Contributions',
        'manage_options',
        'user-contributions',
        'hs_render_contributions_admin_page'
    );
}
add_action('admin_menu', 'hs_register_contributions_admin_menu');

/**
 * Get counts for all contribution types
 */
function hs_get_all_contributions_counts() {
    global $wpdb;

    $counts = [
        'chapters' => [
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_chapter_submissions WHERE status = 'pending'"),
            'approved' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_chapter_submissions WHERE status = 'approved'"),
            'rejected' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_chapter_submissions WHERE status = 'rejected'")
        ],
        'characters' => [
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_character_submissions WHERE status = 'pending'"),
            'approved' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_character_submissions WHERE status = 'approved'"),
            'rejected' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_character_submissions WHERE status = 'rejected'")
        ],
        'tags' => [
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_tag_suggestions WHERE status = 'pending'"),
            'approved' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_tag_suggestions WHERE status = 'approved'"),
            'rejected' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_tag_suggestions WHERE status = 'rejected'")
        ],
        'summaries' => [
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_chapter_summaries WHERE status = 'pending'"),
            'approved' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_chapter_summaries WHERE status = 'approved'"),
            'rejected' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_chapter_summaries WHERE status = 'rejected'")
        ]
    ];

    $counts['total_pending'] = $counts['chapters']['pending'] + $counts['characters']['pending'] +
                                $counts['tags']['pending'] + $counts['summaries']['pending'];

    return $counts;
}

/**
 * Render the contributions admin page
 */
function hs_render_contributions_admin_page() {
    $counts = hs_get_all_contributions_counts();
    ?>
    <div class="wrap">
        <h1>User Contributions
            <?php if ($counts['total_pending'] > 0): ?>
                <span class="update-plugins count-<?php echo $counts['total_pending']; ?>">
                    <span class="update-count"><?php echo $counts['total_pending']; ?></span>
                </span>
            <?php endif; ?>
        </h1>

        <p>Review user-submitted content for books. Users earn points for approved contributions.</p>

        <!-- Type Tabs -->
        <div class="contributions-type-tabs">
            <h2 class="nav-tab-wrapper">
                <a href="#chapters" class="nav-tab nav-tab-active" data-type="chapters">
                    Chapter Titles (<?php echo $counts['chapters']['pending']; ?>) <span class="points-badge">+10 pts</span>
                </a>
                <a href="#characters" class="nav-tab" data-type="characters">
                    Characters (<?php echo $counts['characters']['pending']; ?>) <span class="points-badge">+15 pts</span>
                </a>
                <a href="#tags" class="nav-tab" data-type="tags">
                    Tags (<?php echo $counts['tags']['pending']; ?>) <span class="points-badge">+3 pts</span>
                </a>
                <a href="#summaries" class="nav-tab" data-type="summaries">
                    Chapter Summaries (<?php echo $counts['summaries']['pending']; ?>) <span class="points-badge">+25 pts</span>
                </a>
            </h2>
        </div>

        <!-- Chapters Tab -->
        <div id="chapters-section" class="contribution-type-section active">
            <?php hs_render_contribution_status_tabs('chapters', $counts['chapters']); ?>
        </div>

        <!-- Characters Tab -->
        <div id="characters-section" class="contribution-type-section" style="display:none;">
            <?php hs_render_contribution_status_tabs('characters', $counts['characters']); ?>
        </div>

        <!-- Tags Tab -->
        <div id="tags-section" class="contribution-type-section" style="display:none;">
            <?php hs_render_contribution_status_tabs('tags', $counts['tags']); ?>
        </div>

        <!-- Summaries Tab -->
        <div id="summaries-section" class="contribution-type-section" style="display:none;">
            <?php hs_render_contribution_status_tabs('summaries', $counts['summaries']); ?>
        </div>
    </div>

    <?php hs_render_contributions_styles(); ?>
    <?php hs_render_contributions_scripts(); ?>
    <?php
}

/**
 * Render status tabs for a contribution type
 */
function hs_render_contribution_status_tabs($type, $counts) {
    ?>
    <div class="status-tabs">
        <h3 class="nav-tab-wrapper">
            <a href="#<?php echo $type; ?>-pending" class="nav-tab nav-tab-active" data-status="pending" data-type="<?php echo $type; ?>">
                Pending (<?php echo $counts['pending']; ?>)
            </a>
            <a href="#<?php echo $type; ?>-approved" class="nav-tab" data-status="approved" data-type="<?php echo $type; ?>">
                Approved (<?php echo $counts['approved']; ?>)
            </a>
            <a href="#<?php echo $type; ?>-rejected" class="nav-tab" data-status="rejected" data-type="<?php echo $type; ?>">
                Rejected (<?php echo $counts['rejected']; ?>)
            </a>
        </h3>
    </div>

    <div id="<?php echo $type; ?>-pending-content" class="status-content active">
        <?php
        switch ($type) {
            case 'chapters':
                hs_render_chapters_table('pending');
                break;
            case 'characters':
                hs_render_characters_table('pending');
                break;
            case 'tags':
                hs_render_tags_table('pending');
                break;
            case 'summaries':
                hs_render_summaries_table('pending');
                break;
        }
        ?>
    </div>

    <div id="<?php echo $type; ?>-approved-content" class="status-content" style="display:none;">
        <?php
        switch ($type) {
            case 'chapters':
                hs_render_chapters_table('approved');
                break;
            case 'characters':
                hs_render_characters_table('approved');
                break;
            case 'tags':
                hs_render_tags_table('approved');
                break;
            case 'summaries':
                hs_render_summaries_table('approved');
                break;
        }
        ?>
    </div>

    <div id="<?php echo $type; ?>-rejected-content" class="status-content" style="display:none;">
        <?php
        switch ($type) {
            case 'chapters':
                hs_render_chapters_table('rejected');
                break;
            case 'characters':
                hs_render_characters_table('rejected');
                break;
            case 'tags':
                hs_render_tags_table('rejected');
                break;
            case 'summaries':
                hs_render_summaries_table('rejected');
                break;
        }
        ?>
    </div>
    <?php
}

/**
 * Render chapters submissions table
 */
function hs_render_chapters_table($status) {
    $submissions = hs_get_chapter_submissions_admin($status, 100, 0);

    if (empty($submissions)) {
        echo '<p>No chapter submissions with status: ' . esc_html($status) . '</p>';
        return;
    }

    include __DIR__ . '/tables/chapters_table.php';
}

/**
 * Render characters submissions table
 */
function hs_render_characters_table($status) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_character_submissions';

    $submissions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE status = %s ORDER BY submitted_at DESC LIMIT 100",
        $status
    ));

    foreach ($submissions as $submission) {
        if (!empty($submission->characters_data)) {
            $submission->characters = json_decode($submission->characters_data, true);
        }
        $book = get_post($submission->book_id);
        $submission->book_title = $book ? $book->post_title : 'Unknown Book';
        $submission->book_author = $book ? get_post_meta($book->ID, 'book_author', true) : '';
        $user = get_userdata($submission->user_id);
        $submission->submitter_name = $user ? $user->display_name : 'Unknown User';
        if ($submission->reviewed_by) {
            $reviewer = get_userdata($submission->reviewed_by);
            $submission->reviewer_name = $reviewer ? $reviewer->display_name : 'Unknown';
        }
    }

    if (empty($submissions)) {
        echo '<p>No character submissions with status: ' . esc_html($status) . '</p>';
        return;
    }

    include __DIR__ . '/tables/characters_table.php';
}

/**
 * Render tags suggestions table
 */
function hs_render_tags_table($status) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_tag_suggestions';

    $submissions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE status = %s ORDER BY submitted_at DESC LIMIT 100",
        $status
    ));

    foreach ($submissions as $submission) {
        $book = get_post($submission->book_id);
        $submission->book_title = $book ? $book->post_title : 'Unknown Book';
        $user = get_userdata($submission->user_id);
        $submission->submitter_name = $user ? $user->display_name : 'Unknown User';
        if ($submission->reviewed_by) {
            $reviewer = get_userdata($submission->reviewed_by);
            $submission->reviewer_name = $reviewer ? $reviewer->display_name : 'Unknown';
        }
    }

    if (empty($submissions)) {
        echo '<p>No tag suggestions with status: ' . esc_html($status) . '</p>';
        return;
    }

    include __DIR__ . '/tables/tags_table.php';
}

/**
 * Render chapter summaries table
 */
function hs_render_summaries_table($status) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_summaries';

    $submissions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE status = %s ORDER BY submitted_at DESC LIMIT 100",
        $status
    ));

    foreach ($submissions as $submission) {
        $book = get_post($submission->book_id);
        $submission->book_title = $book ? $book->post_title : 'Unknown Book';
        $user = get_userdata($submission->user_id);
        $submission->submitter_name = $user ? $user->display_name : 'Unknown User';
        if ($submission->reviewed_by) {
            $reviewer = get_userdata($submission->reviewed_by);
            $submission->reviewer_name = $reviewer ? $reviewer->display_name : 'Unknown';
        }
    }

    if (empty($submissions)) {
        echo '<p>No chapter summaries with status: ' . esc_html($status) . '</p>';
        return;
    }

    include __DIR__ . '/tables/summaries_table.php';
}

// Include AJAX handlers and styles/scripts functions
require_once __DIR__ . '/contributions_ajax.php';
require_once __DIR__ . '/contributions_styles.php';
require_once __DIR__ . '/contributions_scripts.php';
