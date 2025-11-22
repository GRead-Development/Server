<?php
/**
 * User ISBN Selector - Front-end Interface
 * Allows users to select which edition/ISBN they own when viewing books on the website
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display ISBN selector on single book pages
 * Can be called from theme templates or as a shortcode
 */
function hs_display_isbn_selector($book_id = null)
{
    if (!$book_id) {
        $book_id = get_the_ID();
    }

    if (!is_user_logged_in()) {
        return '';
    }

    $user_id = get_current_user_id();
    $book_data = hs_get_book_for_user($book_id, $user_id);

    if (!$book_data || !$book_data['has_multiple_editions']) {
        return '';
    }

    ob_start();
    ?>
    <div class="hs-isbn-selector" data-book-id="<?php echo esc_attr($book_id); ?>">
        <div class="hs-isbn-selector-header">
            <h3>
                Multiple Editions Available
                <span class="hs-isbn-count">(<?php echo count($book_data['available_isbns']); ?> editions)</span>
            </h3>
            <p class="hs-isbn-current">
                <?php if ($book_data['user_isbn']): ?>
                    <strong>Your edition:</strong> <?php echo esc_html($book_data['active_isbn']); ?>
                    <?php if ($book_data['active_edition']): ?>
                        - <?php echo esc_html($book_data['active_edition']); ?>
                    <?php endif; ?>
                    (<?php echo number_format($book_data['page_count']); ?> pages)
                <?php else: ?>
                    <strong>Default edition selected.</strong> Click below to choose your specific edition.
                <?php endif; ?>
            </p>
            <button type="button" class="hs-toggle-editions button">
                <span class="hs-toggle-text-show">Choose Your Edition</span>
                <span class="hs-toggle-text-hide" style="display: none;">Hide Editions</span>
            </button>
        </div>

        <div class="hs-isbn-options" style="display: none;">
            <?php foreach ($book_data['available_isbns'] as $isbn_record): ?>
                <?php
                $is_selected = ($isbn_record->isbn === $book_data['user_isbn']);
                $is_primary = $isbn_record->is_primary;
                $page_count = get_field('nop', $isbn_record->post_id);
                ?>
                <div class="hs-isbn-option <?php echo $is_selected ? 'selected' : ''; ?>" data-isbn="<?php echo esc_attr($isbn_record->isbn); ?>">
                    <div class="hs-isbn-option-content">
                        <div class="hs-isbn-radio">
                            <input type="radio"
                                   name="selected_isbn"
                                   value="<?php echo esc_attr($isbn_record->isbn); ?>"
                                   id="isbn-<?php echo esc_attr($isbn_record->isbn); ?>"
                                   <?php checked($is_selected); ?>>
                            <label for="isbn-<?php echo esc_attr($isbn_record->isbn); ?>"></label>
                        </div>
                        <div class="hs-isbn-details">
                            <div class="hs-isbn-number">
                                <strong>ISBN:</strong> <?php echo esc_html($isbn_record->isbn); ?>
                                <?php if ($is_primary): ?>
                                    <span class="hs-isbn-badge primary">Primary</span>
                                <?php endif; ?>
                                <?php if ($is_selected): ?>
                                    <span class="hs-isbn-badge selected">Your Edition</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($isbn_record->edition): ?>
                                <div class="hs-isbn-edition">
                                    <strong>Edition:</strong> <?php echo esc_html($isbn_record->edition); ?>
                                </div>
                            <?php endif; ?>
                            <div class="hs-isbn-meta">
                                <?php if ($isbn_record->publication_year): ?>
                                    <span class="hs-isbn-year">Published: <?php echo esc_html($isbn_record->publication_year); ?></span>
                                <?php endif; ?>
                                <?php if ($page_count): ?>
                                    <span class="hs-isbn-pages"><?php echo number_format($page_count); ?> pages</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="hs-isbn-selector-footer">
            <button type="button" class="hs-isbn-save-btn button" disabled>Save Selection</button>
            <span class="hs-isbn-status"></span>
        </div>
    </div>

    <style>
        .hs-isbn-selector {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .hs-isbn-selector-header h3 {
            margin: 0 0 10px 0;
            font-size: 1.3em;
        }

        .hs-isbn-count {
            color: #666;
            font-size: 0.85em;
            font-weight: normal;
        }

        .hs-isbn-current {
            color: #333;
            margin: 10px 0 15px 0;
            padding: 10px;
            background: white;
            border-left: 3px solid #2271b1;
            border-radius: 4px;
        }

        .hs-toggle-editions {
            margin-bottom: 15px;
        }

        .hs-isbn-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 15px;
            margin-bottom: 15px;
        }

        .hs-isbn-option {
            background: white;
            border: 2px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .hs-isbn-option:hover {
            border-color: #2271b1;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .hs-isbn-option.selected {
            border-color: #2271b1;
            background: #f0f6fc;
        }

        .hs-isbn-option-content {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .hs-isbn-radio {
            flex-shrink: 0;
        }

        .hs-isbn-radio input[type="radio"] {
            width: 20px;
            height: 20px;
            margin: 0;
            cursor: pointer;
        }

        .hs-isbn-radio label {
            display: none;
        }

        .hs-isbn-details {
            flex: 1;
        }

        .hs-isbn-number {
            font-size: 1.05em;
            margin-bottom: 8px;
        }

        .hs-isbn-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 0.85em;
            font-weight: normal;
            margin-left: 8px;
        }

        .hs-isbn-badge.primary {
            background: #e3f2fd;
            color: #1976d2;
        }

        .hs-isbn-badge.selected {
            background: #2271b1;
            color: white;
        }

        .hs-isbn-edition {
            color: #555;
            margin-bottom: 5px;
        }

        .hs-isbn-meta {
            display: flex;
            gap: 15px;
            color: #666;
            font-size: 0.9em;
        }

        .hs-isbn-selector-footer {
            display: flex;
            align-items: center;
            gap: 15px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }

        .hs-isbn-save-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .hs-isbn-status {
            color: #666;
            font-size: 0.9em;
        }

        .hs-isbn-status.success {
            color: #008000;
        }

        .hs-isbn-status.error {
            color: #d63301;
        }

        @media (max-width: 768px) {
            .hs-isbn-selector {
                padding: 15px;
            }

            .hs-isbn-option-content {
                flex-direction: row;
            }

            .hs-isbn-meta {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>

    <script>
        (function($) {
            $(document).ready(function() {
                const $selector = $('.hs-isbn-selector');
                const $options = $selector.find('.hs-isbn-option');
                const $optionsContainer = $selector.find('.hs-isbn-options');
                const $toggleBtn = $selector.find('.hs-toggle-editions');
                const $toggleTextShow = $toggleBtn.find('.hs-toggle-text-show');
                const $toggleTextHide = $toggleBtn.find('.hs-toggle-text-hide');
                const $saveBtn = $selector.find('.hs-isbn-save-btn');
                const $status = $selector.find('.hs-isbn-status');
                const bookId = $selector.data('book-id');
                let originalSelection = $selector.find('input[type="radio"]:checked').val();

                // Handle toggle button
                $toggleBtn.on('click', function() {
                    if ($optionsContainer.is(':visible')) {
                        $optionsContainer.slideUp(200);
                        $toggleTextShow.show();
                        $toggleTextHide.hide();
                    } else {
                        $optionsContainer.slideDown(200);
                        $toggleTextShow.hide();
                        $toggleTextHide.show();
                    }
                });

                // Handle option click
                $options.on('click', function() {
                    const isbn = $(this).data('isbn');
                    const $radio = $(this).find('input[type="radio"]');

                    // Update selection
                    $options.removeClass('selected');
                    $(this).addClass('selected');
                    $radio.prop('checked', true);

                    // Enable save button if selection changed
                    if (isbn !== originalSelection) {
                        $saveBtn.prop('disabled', false);
                        $status.text('');
                    } else {
                        $saveBtn.prop('disabled', true);
                    }
                });

                // Handle radio click (prevent double-toggle)
                $selector.find('input[type="radio"]').on('click', function(e) {
                    e.stopPropagation();
                });

                // Handle save
                $saveBtn.on('click', function() {
                    const selectedIsbn = $selector.find('input[type="radio"]:checked').val();

                    if (!selectedIsbn) {
                        return;
                    }

                    $saveBtn.prop('disabled', true).text('Saving...');
                    $status.removeClass('success error').text('');

                    // Use REST API
                    $.ajax({
                        url: '/wp-json/gread/v1/books/' + bookId + '/my-isbn',
                        method: 'POST',
                        data: JSON.stringify({
                            isbn: selectedIsbn
                        }),
                        contentType: 'application/json',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                        },
                        success: function(response) {
                            $status.addClass('success').text('✓ Saved! Page will reload...');
                            originalSelection = selectedIsbn;

                            // Reload page after short delay to show updated page count
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        },
                        error: function(xhr) {
                            let errorMsg = 'Failed to save selection';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            $status.addClass('error').text('✗ ' + errorMsg);
                            $saveBtn.prop('disabled', false).text('Save Selection');
                        }
                    });
                });
            });
        })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode for ISBN selector
 * Usage: [isbn_selector] or [isbn_selector book_id="123"]
 */
function hs_isbn_selector_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'book_id' => get_the_ID()
    ), $atts);

    return hs_display_isbn_selector($atts['book_id']);
}
add_shortcode('isbn_selector', 'hs_isbn_selector_shortcode');

/**
 * Display compact edition info (shows what edition user has selected)
 * Usage: [my_edition] or [my_edition book_id="123"]
 */
function hs_my_edition_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'book_id' => get_the_ID()
    ), $atts);

    if (!is_user_logged_in()) {
        return '';
    }

    $user_id = get_current_user_id();
    $book_data = hs_get_book_for_user($atts['book_id'], $user_id);

    if (!$book_data) {
        return '';
    }

    ob_start();
    ?>
    <div class="hs-my-edition">
        <?php if ($book_data['has_multiple_editions']): ?>
            <div class="hs-edition-info">
                <strong>Your Edition:</strong>
                <span class="hs-edition-isbn"><?php echo esc_html($book_data['active_isbn']); ?></span>
                <?php if ($book_data['active_edition']): ?>
                    <span class="hs-edition-name">(<?php echo esc_html($book_data['active_edition']); ?>)</span>
                <?php endif; ?>
                <?php if ($book_data['page_count']): ?>
                    <span class="hs-edition-pages">- <?php echo number_format($book_data['page_count']); ?> pages</span>
                <?php endif; ?>
                <?php if (!$book_data['user_isbn']): ?>
                    <span class="hs-edition-note">(default edition, <a href="#hs-isbn-selector">select yours</a>)</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <style>
        .hs-my-edition {
            padding: 10px 15px;
            background: #f0f6fc;
            border-left: 3px solid #2271b1;
            margin: 15px 0;
            border-radius: 4px;
        }
        .hs-edition-info {
            font-size: 0.95em;
        }
        .hs-edition-note {
            color: #666;
            font-style: italic;
        }
        .hs-edition-note a {
            color: #2271b1;
            text-decoration: underline;
        }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('my_edition', 'hs_my_edition_shortcode');

/**
 * Automatically add ISBN selector to single book pages
 * Hooks into the_content filter
 */
function hs_auto_add_isbn_selector($content)
{
    // Only on single book posts
    if (!is_singular('book') || !is_main_query()) {
        return $content;
    }

    // Check if user is logged in
    if (!is_user_logged_in()) {
        return $content;
    }

    $book_id = get_the_ID();
    $user_id = get_current_user_id();

    // Ensure book's ISBN is in the database table
    $gid = hs_get_or_create_gid($book_id);
    hs_ensure_isbn_in_table($book_id, $gid);

    $book_data = hs_get_book_for_user($book_id, $user_id);

    // Only show if book has multiple editions
    if (!$book_data || !$book_data['has_multiple_editions']) {
        return $content;
    }

    // Add selector before the content
    $selector_html = hs_display_isbn_selector($book_id);

    return $selector_html . $content;
}
add_filter('the_content', 'hs_auto_add_isbn_selector', 20);

/**
 * Display page count with edition awareness
 * Override the default page count display to show the correct count for user's edition
 */
function hs_get_user_page_count($book_id = null, $user_id = null)
{
    if (!$book_id) {
        $book_id = get_the_ID();
    }

    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        // Not logged in, return default page count
        return get_field('nop', $book_id);
    }

    $book_data = hs_get_book_for_user($book_id, $user_id);

    return $book_data ? $book_data['page_count'] : get_field('nop', $book_id);
}
