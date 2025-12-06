/**
 * Contribution Modals JavaScript
 * Handles characters, tags, and chapter summaries submissions
 */

jQuery(document).ready(function($) {
    // ===== CHARACTERS SUBMISSION =====
    $('.hs-submit-characters-btn').click(function() {
        const bookId = $(this).data('book-id');
        openCharactersModal(bookId);
    });

    function openCharactersModal(bookId) {
        const modal = `
            <div class="hs-modal-overlay">
                <div class="hs-modal-content">
                    <span class="hs-modal-close">&times;</span>
                    <h2>Submit Characters</h2>
                    <p>Add character names from this book. Earn 15 points when approved!</p>
                    <div id="characters-list"></div>
                    <button type="button" class="add-character-btn">+ Add Character</button>
                    <div class="modal-actions">
                        <button type="button" class="btn-primary" id="submit-characters-btn" data-book-id="${bookId}">Submit for Review</button>
                        <button type="button" class="btn-secondary hs-modal-close">Cancel</button>
                    </div>
                    <div class="modal-message"></div>
                </div>
            </div>
        `;
        $('body').append(modal);
        addCharacterRow();
        addCharacterRow();
        addCharacterRow();
    }

    function addCharacterRow() {
        const row = `
            <div class="char-row">
                <input type="text" class="char-name" placeholder="Character name" required>
                <input type="text" class="char-desc" placeholder="Description (optional)">
                <button type="button" class="remove-row">&times;</button>
            </div>
        `;
        $('#characters-list').append(row);
    }

    $(document).on('click', '.add-character-btn', addCharacterRow);

    $(document).on('click', '#submit-characters-btn', function() {
        const bookId = $(this).data('book-id');
        const characters = [];

        $('.char-row').each(function() {
            const name = $(this).find('.char-name').val().trim();
            const desc = $(this).find('.char-desc').val().trim();
            if (name) {
                characters.push({ name, description: desc });
            }
        });

        if (characters.length === 0) {
            showModalMessage('Please add at least one character.', 'error');
            return;
        }

        $(this).prop('disabled', true).text('Submitting...');

        $.ajax({
            url: '/wp-json/gread/v1/books/' + bookId + '/characters/submit',
            method: 'POST',
            beforeSend: function(xhr) {
                if (typeof wpApiSettings !== 'undefined') {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                }
            },
            contentType: 'application/json',
            data: JSON.stringify({ characters }),
            success: function(r) {
                if (r.success) {
                    showModalMessage(r.message, 'success');
                    setTimeout(() => { $('.hs-modal-overlay').remove(); location.reload(); }, 2000);
                } else {
                    showModalMessage(r.message, 'error');
                    $('#submit-characters-btn').prop('disabled', false).text('Submit for Review');
                }
            },
            error: function(xhr) {
                showModalMessage(xhr.responseJSON?.message || 'Error occurred', 'error');
                $('#submit-characters-btn').prop('disabled', false).text('Submit for Review');
            }
        });
    });

    // ===== TAG SUGGESTIONS =====
    $('.hs-submit-tags-btn').click(function() {
        const bookId = $(this).data('book-id');
        openTagsModal(bookId);
    });

    function openTagsModal(bookId) {
        const modal = `
            <div class="hs-modal-overlay">
                <div class="hs-modal-content">
                    <span class="hs-modal-close">&times;</span>
                    <h2>Suggest Tags</h2>
                    <p>Suggest tags for this book. Earn 3 points per approved tag!</p>
                    <div class="tags-input-wrapper">
                        <input type="text" id="tag-input" placeholder="Enter tags (press Enter to add)">
                        <div id="tags-container"></div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-primary" id="submit-tags-btn" data-book-id="${bookId}">Submit Tags</button>
                        <button type="button" class="btn-secondary hs-modal-close">Cancel</button>
                    </div>
                    <div class="modal-message"></div>
                </div>
            </div>
        `;
        const $modal = $(modal);
        $('body').append($modal);

        let tags = [];
        $modal.find('#tag-input').on('keypress', function(e) {
            if (e.which === 13 || e.which === 44) { // Enter or comma
                e.preventDefault();
                const tag = $(this).val().trim();
                if (tag && !tags.includes(tag)) {
                    tags.push(tag);
                    $modal.find('#tags-container').append(`
                        <span class="tag-pill">${tag} <span class="remove-tag" data-tag="${tag}">&times;</span></span>
                    `);
                    $(this).val('');
                }
            }
        });

        $modal.on('click', '.remove-tag', function() {
            const tag = $(this).data('tag');
            tags = tags.filter(t => t !== tag);
            $(this).closest('.tag-pill').remove();
        });

        $modal.find('#submit-tags-btn').on('click', function() {
            if (tags.length === 0) {
                showModalMessage('Please add at least one tag.', 'error');
                return;
            }

            $(this).prop('disabled', true).text('Submitting...');

            $.ajax({
                url: '/wp-json/gread/v1/books/' + bookId + '/tags/suggest',
                method: 'POST',
                beforeSend: function(xhr) {
                    if (typeof wpApiSettings !== 'undefined') {
                        xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                    }
                },
                contentType: 'application/json',
                data: JSON.stringify({ tags }),
                success: function(r) {
                    if (r.success) {
                        showModalMessage(r.message, 'success');
                        setTimeout(() => { $modal.fadeOut(200, function() { $(this).remove(); }); location.reload(); }, 2000);
                    } else {
                        showModalMessage(r.message, 'error');
                        $modal.find('#submit-tags-btn').prop('disabled', false).text('Submit Tags');
                    }
                },
                error: function(xhr) {
                    showModalMessage(xhr.responseJSON?.message || 'Error occurred', 'error');
                    $modal.find('#submit-tags-btn').prop('disabled', false).text('Submit Tags');
                }
            });
        });
    }

    // ===== CHAPTER SUMMARY =====
    $('.hs-submit-summary-btn').click(function() {
        const bookId = $(this).data('book-id');
        openSummaryModal(bookId);
    });

    function openSummaryModal(bookId) {
        const modal = `
            <div class="hs-modal-overlay">
                <div class="hs-modal-content summary-modal">
                    <span class="hs-modal-close">&times;</span>
                    <h2>Write Chapter Summary</h2>
                    <p>Write a summary for a specific chapter. Earn 25 points when approved!</p>
                    <div class="form-group">
                        <label>Chapter Number</label>
                        <input type="number" id="chapter-num" min="1" placeholder="e.g., 1" required>
                    </div>
                    <div class="form-group">
                        <label>Chapter Title (optional)</label>
                        <input type="text" id="chapter-title" placeholder="e.g., The Beginning">
                    </div>
                    <div class="form-group">
                        <label>Summary (minimum 50 characters)</label>
                        <textarea id="chapter-summary" rows="6" placeholder="Write a brief summary of what happens in this chapter..." required></textarea>
                        <small class="char-count">0 / 50 characters</small>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-primary" id="submit-summary-btn" data-book-id="${bookId}">Submit Summary</button>
                        <button type="button" class="btn-secondary hs-modal-close">Cancel</button>
                    </div>
                    <div class="modal-message"></div>
                </div>
            </div>
        `;
        const $modal = $(modal);
        $('body').append($modal);

        $modal.find('#chapter-summary').on('input', function() {
            const len = $(this).val().length;
            $modal.find('.char-count').text(len + ' / 50 characters');
        });

        $modal.find('#submit-summary-btn').on('click', function() {
            const chapterNum = parseInt($modal.find('#chapter-num').val());
            const chapterTitle = $modal.find('#chapter-title').val().trim();
            const summary = $modal.find('#chapter-summary').val().trim();

            if (!chapterNum || chapterNum < 1) {
                showModalMessage('Please enter a valid chapter number.', 'error');
                return;
            }

            if (summary.length < 50) {
                showModalMessage('Summary must be at least 50 characters.', 'error');
                return;
            }

            $(this).prop('disabled', true).text('Submitting...');

            $.ajax({
                url: '/wp-json/gread/v1/books/' + bookId + '/summaries/submit',
                method: 'POST',
                beforeSend: function(xhr) {
                    if (typeof wpApiSettings !== 'undefined') {
                        xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                    }
                },
                contentType: 'application/json',
                data: JSON.stringify({
                    chapter_number: chapterNum,
                    chapter_title: chapterTitle,
                    summary: summary
                }),
                success: function(r) {
                    if (r.success) {
                        showModalMessage(r.message, 'success');
                        setTimeout(() => { $modal.fadeOut(200, function() { $(this).remove(); }); location.reload(); }, 2000);
                    } else {
                        showModalMessage(r.message, 'error');
                        $modal.find('#submit-summary-btn').prop('disabled', false).text('Submit Summary');
                    }
                },
                error: function(xhr) {
                    showModalMessage(xhr.responseJSON?.message || 'Error occurred', 'error');
                    $modal.find('#submit-summary-btn').prop('disabled', false).text('Submit Summary');
                }
            });
        });
    }

    // ===== COMMON FUNCTIONS =====
    $(document).on('click', '.hs-modal-close, .hs-modal-overlay', function(e) {
        if (e.target === this) {
            $('.hs-modal-overlay').fadeOut(200, function() { $(this).remove(); });
        }
    });

    $(document).on('click', '.remove-row', function() {
        $(this).closest('.char-row').fadeOut(200, function() { $(this).remove(); });
    });

    function showModalMessage(msg, type) {
        const className = type === 'success' ? 'success-msg' : 'error-msg';
        $('.modal-message').removeClass('success-msg error-msg').addClass(className).html(msg).fadeIn();
        if (type === 'error') {
            setTimeout(() => $('.modal-message').fadeOut(), 5000);
        }
    }
});
