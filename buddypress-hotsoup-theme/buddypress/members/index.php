<?php
/**
 * BuddyPress Members Index Template
 *
 * @package BP_HotSoup_Theme
 */
?>

<div id="buddypress" class="buddypress-wrap">
    <?php if (bp_has_members(bp_ajax_querystring('members'))) : ?>

        <div id="members-dir-list" class="members dir-list">
            <ul id="members-list" class="item-list members-list">
                <?php
                while (bp_members()) :
                    bp_the_member();
                ?>
                    <li <?php bp_member_class(); ?>>
                        <div class="item-avatar">
                            <a href="<?php bp_member_permalink(); ?>"><?php bp_member_avatar('type=full&width=150&height=150'); ?></a>
                        </div>

                        <div class="item">
                            <div class="item-title">
                                <a href="<?php bp_member_permalink(); ?>"><?php bp_member_name(); ?></a>
                            </div>

                            <div class="item-meta">
                                <span class="activity"><?php bp_member_last_active(); ?></span>
                            </div>

                            <?php do_action('bp_directory_members_item'); ?>
                        </div>

                        <div class="action">
                            <?php do_action('bp_directory_members_actions'); ?>
                        </div>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>

    <?php else : ?>

        <div class="content-card">
            <p><?php _e('Sorry, no members were found.', 'bp-hotsoup-theme'); ?></p>
        </div>

    <?php endif; ?>
</div>
