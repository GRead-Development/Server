<?php
/**
 * The sidebar template
 *
 * @package BP_HotSoup_Theme
 */

if (!is_active_sidebar('sidebar-1')) {
    return;
}
?>

<aside class="sidebar" role="complementary" aria-label="<?php esc_attr_e('Sidebar', 'bp-hotsoup-theme'); ?>">
    <?php dynamic_sidebar('sidebar-1'); ?>
</aside>
