<?php
/**
 * BuddyPress Activity Index Template
 *
 * @package BP_HotSoup_Theme
 */
?>

<div id="buddypress" class="buddypress-wrap">
    <?php if (bp_has_activities(bp_ajax_querystring('activity'))) : ?>

        <div class="activity-filter-wrapper">
            <?php bp_get_template_part('activity/activity-loop'); ?>
        </div>

    <?php else : ?>

        <div class="content-card">
            <p><?php _e('Sorry, there was no activity found. Please try a different filter.', 'bp-hotsoup-theme'); ?></p>
        </div>

    <?php endif; ?>
</div>
