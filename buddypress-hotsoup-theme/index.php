<?php
/**
 * The main template file
 *
 * @package BP_HotSoup_Theme
 */

get_header();
?>

<div class="content-area">
    <div class="main-content">
        <?php if (have_posts()) : ?>

            <?php if (is_home() && !is_front_page()) : ?>
                <header class="page-header">
                    <h1 class="page-title"><?php single_post_title(); ?></h1>
                </header>
            <?php endif; ?>

            <?php
            while (have_posts()) :
                the_post();
                ?>

                <article id="post-<?php the_ID(); ?>" <?php post_class('content-card'); ?>>
                    <header class="entry-header">
                        <?php
                        if (is_singular()) :
                            the_title('<h1 class="entry-title">', '</h1>');
                        else :
                            the_title('<h2 class="entry-title"><a href="' . esc_url(get_permalink()) . '" rel="bookmark">', '</a></h2>');
                        endif;
                        ?>

                        <?php if ('post' === get_post_type()) : ?>
                            <div class="entry-meta">
                                <span class="posted-on">
                                    <?php echo get_the_date(); ?>
                                </span>
                                <span class="byline">
                                    by <?php the_author_posts_link(); ?>
                                </span>
                                <?php if (has_category()) : ?>
                                    <span class="cat-links">
                                        in <?php the_category(', '); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </header>

                    <?php if (has_post_thumbnail() && !is_singular()) : ?>
                        <div class="post-thumbnail">
                            <a href="<?php the_permalink(); ?>">
                                <?php the_post_thumbnail('large'); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="entry-content">
                        <?php
                        if (is_singular()) :
                            the_content();

                            wp_link_pages(array(
                                'before' => '<div class="page-links">' . __('Pages:', 'bp-hotsoup-theme'),
                                'after'  => '</div>',
                            ));
                        else :
                            the_excerpt();
                        ?>
                            <a href="<?php the_permalink(); ?>" class="read-more">
                                <?php _e('Read More', 'bp-hotsoup-theme'); ?> &rarr;
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (is_singular() && (has_tag() || has_category())) : ?>
                        <footer class="entry-footer">
                            <?php
                            if (has_tag()) :
                                the_tags('<div class="tags-links"><strong>' . __('Tags:', 'bp-hotsoup-theme') . '</strong> ', ', ', '</div>');
                            endif;
                            ?>
                        </footer>
                    <?php endif; ?>
                </article>

                <?php
                if (is_singular() && comments_open()) :
                    comments_template();
                endif;
                ?>

            <?php endwhile; ?>

            <?php bp_hotsoup_pagination(); ?>

        <?php else : ?>

            <div class="content-card">
                <h1><?php _e('Nothing Found', 'bp-hotsoup-theme'); ?></h1>
                <p><?php _e('Sorry, no content was found. Try searching for what you are looking for.', 'bp-hotsoup-theme'); ?></p>
                <?php get_search_form(); ?>
            </div>

        <?php endif; ?>
    </div>

    <?php get_sidebar(); ?>
</div>

<?php
get_footer();
