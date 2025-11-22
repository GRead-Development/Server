<?php
/**
 * Notes Modal HTML Template and JavaScript
 *
 * Outputs the modal HTML for viewing and managing notes
 * Enqueues JavaScript for modal functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue the notes modal CSS and JavaScript
 */
function hs_enqueue_notes_modal_assets() {
    if (is_user_logged_in()) {
        wp_enqueue_script('hs-notes-modal', plugin_dir_url(__FILE__) . '../../js/notes-modal.js', array('jquery'), '1.0', true);
        wp_enqueue_style('hs-notes-modal', plugin_dir_url(__FILE__) . '../../css/notes-modal.css', array(), '1.0');

        // Localize script with AJAX nonce
        wp_localize_script('hs-notes-modal', 'hsNotesModal', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hs_notes_action'),
            'userId' => get_current_user_id()
        ));
    }
}
add_action('wp_enqueue_scripts', 'hs_enqueue_notes_modal_assets');

/**
 * Output the notes modal HTML template
 */
function hs_output_notes_modal() {
    if (!is_user_logged_in()) {
        return;
    }
    ?>
    <div id="hs-notes-modal" class="hs-modal" style="display: none;">
        <div class="hs-modal-content">
            <span class="hs-modal-close">&times;</span>

            <h2>Notes for <span id="hs-notes-book-title"></span></h2>

            <!-- Tabs for switching between views -->
            <div class="hs-notes-tabs">
                <button class="hs-tab-button active" data-tab="my-notes">My Notes</button>
                <button class="hs-tab-button" data-tab="public-notes">Public Notes</button>
                <button class="hs-tab-button" data-tab="add-note">Add New Note</button>
            </div>

            <!-- My Notes Tab -->
            <div id="my-notes" class="hs-tab-content active">
                <div id="hs-user-notes-list" class="hs-notes-list">
                    <p class="hs-loading">Loading your notes...</p>
                </div>
            </div>

            <!-- Public Notes Tab -->
            <div id="public-notes" class="hs-tab-content">
                <div id="hs-public-notes-list" class="hs-notes-list">
                    <p class="hs-loading">Loading public notes...</p>
                </div>
            </div>

            <!-- Add/Edit Note Tab -->
            <div id="add-note" class="hs-tab-content">
                <form id="hs-note-form" class="hs-note-form">
                    <input type="hidden" id="hs-note-book-id" name="book_id">
                    <input type="hidden" id="hs-note-id" name="note_id" value="">

                    <div class="hs-form-group">
                        <label for="hs-note-text">Note Text:</label>
                        <textarea id="hs-note-text" name="note_text" rows="6" required placeholder="Write your note here..."></textarea>
                    </div>

                    <div class="hs-form-group">
                        <label for="hs-note-page">Page Number (optional):</label>
                        <input type="number" id="hs-note-page" name="page_number" min="1" placeholder="Leave blank if not applicable">
                    </div>

                    <div class="hs-form-group">
                        <label>
                            <input type="checkbox" id="hs-note-public" name="is_public" value="1">
                            Make this note public
                        </label>
                    </div>

                    <div class="hs-form-actions">
                        <button type="submit" class="hs-button hs-button-primary" id="hs-note-submit">Save Note</button>
                        <button type="reset" class="hs-button hs-button-secondary" id="hs-note-cancel">Clear</button>
                        <button type="button" class="hs-button hs-button-danger" id="hs-note-delete" style="display: none;">Delete Note</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'hs_output_notes_modal', 20);
