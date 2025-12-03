<?php
/**
 * Modal-based Book Submission System
 *
 * Provides modal forms for:
 * - Submitting books without ISBN
 * - Viewing user's pending book submissions
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue book submission modal assets
 */
function hs_enqueue_book_submission_assets()
{
    if (is_user_logged_in()) {
        wp_enqueue_script(
            'hs-book-submission-modal',
            plugin_dir_url(__FILE__) . '../../js/book-submission-modal.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'hs-book-submission-modal',
            plugin_dir_url(__FILE__) . '../../css/book-submission-modal.css',
            array(),
            '1.0.0'
        );

        // Localize script with data
        wp_localize_script('hs-book-submission-modal', 'hsBookSubmission', array(
            'restUrl' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id()
        ));
    }
}
add_action('wp_enqueue_scripts', 'hs_enqueue_book_submission_assets');

/**
 * Output book submission modal HTML
 */
function hs_output_book_submission_modal()
{
    if (!is_user_logged_in()) {
        return;
    }

    $current_year = date('Y') + 1;
    ?>

    <!-- Book Submission Modal -->
    <div id="hs-book-submit-modal" class="hs-book-submit-modal">
        <div class="hs-book-modal-content">
            <span class="hs-book-modal-close">&times;</span>

            <h2>Submit a Book Without ISBN</h2>
            <p class="description">
                Can't find your book? Submit it here! If the book doesn't have an ISBN
                (like some older books, self-published books, or e-books), you can add it
                to our database. An admin will review it, but you can start tracking it
                in your library right away!
            </p>

            <div id="hs-book-message-area"></div>

            <form id="hs-book-submit-form">
                <div class="hs-book-form-group">
                    <label for="hs-book-title">
                        Title <span class="required">*</span>
                    </label>
                    <input type="text" id="hs-book-title" name="title" required>
                </div>

                <div class="hs-book-form-group">
                    <label for="hs-book-author">
                        Author <span class="required">*</span>
                    </label>
                    <input type="text" id="hs-book-author" name="author" required>
                    <small>Enter the author's full name (e.g., "J.K. Rowling")</small>
                </div>

                <div class="hs-book-form-group">
                    <label for="hs-book-page-count">
                        Page Count <span class="required">*</span>
                    </label>
                    <input type="number" id="hs-book-page-count" name="page_count" min="1" required>
                </div>

                <div class="hs-book-form-group">
                    <label for="hs-book-description">Description</label>
                    <textarea id="hs-book-description" name="description" rows="3"></textarea>
                    <small>Optional: Brief description of the book</small>
                </div>

                <div class="hs-book-form-group">
                    <label for="hs-book-cover-url">Cover Image URL</label>
                    <input type="url" id="hs-book-cover-url" name="cover_url">
                    <small>Optional: Direct link to the book cover image</small>
                </div>

                <div class="hs-book-form-group">
                    <label for="hs-book-publication-year">Publication Year</label>
                    <input type="number" id="hs-book-publication-year" name="publication_year"
                           min="1000" max="<?php echo esc_attr($current_year); ?>">
                </div>

                <div class="hs-book-form-group">
                    <label for="hs-book-publisher">Publisher</label>
                    <input type="text" id="hs-book-publisher" name="publisher">
                </div>

                <div class="hs-book-form-group">
                    <label for="hs-book-external-id-type">External ID Type</label>
                    <select id="hs-book-external-id-type" name="external_id_type">
                        <option value="">None</option>
                        <option value="ASIN">Amazon ASIN</option>
                        <option value="OCLC">OCLC Number</option>
                        <option value="LCCN">Library of Congress Control Number</option>
                        <option value="Goodreads">Goodreads ID</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="hs-book-form-group" id="hs-external-id-group" style="display: none;">
                    <label for="hs-book-external-id">External ID</label>
                    <input type="text" id="hs-book-external-id" name="external_id">
                    <small id="hs-external-id-help"></small>
                </div>

                <div class="hs-book-form-group">
                    <label>
                        <input type="checkbox" id="hs-add-to-library" name="add_to_library" checked>
                        Add to my library after submission
                    </label>
                </div>

                <div class="hs-book-form-actions">
                    <button type="submit" id="hs-book-submit-btn" class="hs-book-button hs-book-button-primary">
                        Submit Book
                    </button>
                    <button type="reset" class="hs-book-button hs-book-button-secondary">
                        Clear Form
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pending Books Modal -->
    <div id="hs-pending-books-modal" class="hs-pending-books-modal">
        <div class="hs-pending-books-modal-content">
            <span class="hs-book-modal-close hs-pending-modal-close">&times;</span>

            <h2>My Book Submissions</h2>
            <p class="description">
                View the status of your submitted books. Pending books are under review by our admins.
            </p>

            <div id="hs-pending-books-list">
                <div class="hs-no-submissions">Loading...</div>
            </div>
        </div>
    </div>

    <?php
}
add_action('wp_footer', 'hs_output_book_submission_modal', 20);

/**
 * Shortcode to create a "Submit Book" button/link
 * Usage: [submit_book_button]
 *
 * Attributes:
 * - text: Button text (default: "Submit a Book")
 * - class: Additional CSS classes
 */
function hs_submit_book_button_shortcode($atts = [])
{
    if (!is_user_logged_in()) {
        return '<p><em>Please log in to submit books.</em></p>';
    }

    $atts = shortcode_atts(array(
        'text' => 'Submit a Book',
        'class' => ''
    ), $atts);

    $classes = 'hs-submit-book-trigger ' . esc_attr($atts['class']);

    return sprintf(
        '<button class="%s">%s</button>',
        $classes,
        esc_html($atts['text'])
    );
}
add_shortcode('submit_book_button', 'hs_submit_book_button_shortcode');

/**
 * Shortcode to create a "My Submissions" button/link
 * Usage: [my_submissions_button]
 *
 * Attributes:
 * - text: Button text (default: "My Submissions")
 * - class: Additional CSS classes
 */
function hs_my_submissions_button_shortcode($atts = [])
{
    if (!is_user_logged_in()) {
        return '<p><em>Please log in to view submissions.</em></p>';
    }

    $atts = shortcode_atts(array(
        'text' => 'My Submissions',
        'class' => ''
    ), $atts);

    $classes = 'hs-my-submissions-trigger ' . esc_attr($atts['class']);

    return sprintf(
        '<button class="%s">%s</button>',
        $classes,
        esc_html($atts['text'])
    );
}
add_shortcode('my_submissions_button', 'hs_my_submissions_button_shortcode');

/**
 * Shortcode for combined book submission interface
 * Usage: [book_submission_interface]
 *
 * Creates both buttons with a nice layout
 */
function hs_book_submission_interface_shortcode($atts = [])
{
    if (!is_user_logged_in()) {
        return '<div class="hs-book-submission-notice">
            <p>Please log in to submit books or view your submissions.</p>
        </div>';
    }

    ob_start();
    ?>
    <div class="hs-book-submission-interface">
        <style>
            .hs-book-submission-interface {
                text-align: center;
                padding: 30px 20px;
                background: #f5f5f5;
                border-radius: 8px;
                margin: 20px 0;
            }
            .hs-book-submission-interface h3 {
                margin-top: 0;
                margin-bottom: 15px;
                color: #333;
            }
            .hs-book-submission-interface p {
                color: #666;
                margin-bottom: 20px;
            }
            .hs-book-submission-buttons {
                display: flex;
                gap: 15px;
                justify-content: center;
                flex-wrap: wrap;
            }
            @media (max-width: 600px) {
                .hs-book-submission-buttons {
                    flex-direction: column;
                }
                .hs-book-submission-buttons button {
                    width: 100%;
                }
            }
        </style>

        <h3>Book Submission</h3>
        <p>Can't find a book? Submit it to our database! Track your submissions and see when they're approved.</p>

        <div class="hs-book-submission-buttons">
            <button class="hs-submit-book-trigger">📚 Submit a Book</button>
            <button class="hs-my-submissions-trigger">📋 My Submissions</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('book_submission_interface', 'hs_book_submission_interface_shortcode');
