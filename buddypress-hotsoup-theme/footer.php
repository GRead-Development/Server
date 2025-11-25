        </div><!-- .site-container -->
    </main><!-- #main -->

    <footer class="site-footer">
        <div class="site-container">
            <?php if (is_active_sidebar('footer-1')) : ?>
                <div class="footer-widgets">
                    <?php dynamic_sidebar('footer-1'); ?>
                </div>
            <?php endif; ?>

            <div class="site-info">
                <p>
                    &copy; <?php echo date('Y'); ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>">
                        <?php bloginfo('name'); ?>
                    </a>
                    <?php _e('All rights reserved.', 'bp-hotsoup-theme'); ?>
                </p>
                <p>
                    <?php
                    printf(
                        __('Powered by %s', 'bp-hotsoup-theme'),
                        '<a href="https://wordpress.org/" target="_blank" rel="noopener">WordPress</a>'
                    );
                    ?>
                    <?php if (function_exists('buddypress')) : ?>
                        <?php
                        printf(
                            __(' and %s', 'bp-hotsoup-theme'),
                            '<a href="https://buddypress.org/" target="_blank" rel="noopener">BuddyPress</a>'
                        );
                        ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </footer>
</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
