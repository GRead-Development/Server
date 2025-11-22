jQuery(document).ready(function($) {
    const modal = $('#hs-notes-modal');
    const closeBtn = $('.hs-modal-close');
    const tabButtons = $('.hs-tab-button');
    const noteForm = $('#hs-note-form');

    /**
     * Open the notes modal for a specific book
     */
    function openNotesModal(button) {
        const bookId = button.data('book-id');
        const bookTitle = button.data('book-title');

        $('#hs-notes-book-title').text(bookTitle);
        $('#hs-note-book-id').val(bookId);
        $('#hs-note-id').val('');

        // Reset form for new note
        noteForm[0].reset();
        $('#hs-note-delete').hide();

        modal.show();

        // Load notes
        loadUserNotes(bookId);
        loadPublicNotes(bookId);

        // Switch to my notes tab
        switchTab('my-notes');
    }

    /**
     * Close the modal
     */
    function closeNotesModal() {
        modal.hide();
        noteForm[0].reset();
        $('#hs-note-delete').hide();
    }

    /**
     * Load user's notes for a book
     */
    function loadUserNotes(bookId) {
        const notesList = $('#hs-user-notes-list');
        notesList.html('<p class="hs-loading">Loading your notes...</p>');

        $.ajax({
            url: hsNotesModal.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hs_get_user_notes',
                book_id: bookId,
                nonce: hsNotesModal.nonce
            },
            success: function(response) {
                if (response.success) {
                    const notes = response.data;
                    if (notes.length === 0) {
                        notesList.html('<p>You haven\'t added any notes for this book yet.</p>');
                    } else {
                        let html = '<div class="hs-notes-container">';
                        notes.forEach(function(note) {
                            html += renderNoteItem(note, true);
                        });
                        html += '</div>';
                        notesList.html(html);

                        // Add event listeners to edit buttons
                        notesList.find('.hs-edit-note-btn').on('click', function() {
                            editNote($(this).data('note-id'));
                        });
                    }
                } else {
                    notesList.html('<p class="hs-error">Error loading notes: ' + response.data + '</p>');
                }
            },
            error: function() {
                notesList.html('<p class="hs-error">Error loading notes. Please try again.</p>');
            }
        });
    }

    /**
     * Load public notes for a book
     */
    function loadPublicNotes(bookId) {
        const notesList = $('#hs-public-notes-list');
        notesList.html('<p class="hs-loading">Loading public notes...</p>');

        $.ajax({
            url: hsNotesModal.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hs_get_public_notes',
                book_id: bookId,
                nonce: hsNotesModal.nonce
            },
            success: function(response) {
                if (response.success) {
                    const notes = response.data;
                    if (notes.length === 0) {
                        notesList.html('<p>No public notes for this book yet.</p>');
                    } else {
                        let html = '<div class="hs-notes-container">';
                        notes.forEach(function(note) {
                            html += renderNoteItem(note, false);
                        });
                        html += '</div>';
                        notesList.html(html);
                    }
                } else {
                    notesList.html('<p class="hs-error">Error loading notes: ' + response.data + '</p>');
                }
            },
            error: function() {
                notesList.html('<p class="hs-error">Error loading notes. Please try again.</p>');
            }
        });
    }

    /**
     * Render a single note item
     */
    function renderNoteItem(note, isUserNote) {
        let html = '<div class="hs-note-item">';

        if (isUserNote) {
            html += '<div class="hs-note-header">';
            html += '<h4>My Note' + (note.page_number ? ' - Page ' + note.page_number : '') + '</h4>';
            html += '<span class="hs-note-date">' + new Date(note.date_created).toLocaleDateString() + '</span>';
            html += '</div>';
        } else {
            html += '<div class="hs-note-header">';
            html += '<h4>' + escapeHtml(note.display_name) + ' - Page ' + (note.page_number || 'N/A') + '</h4>';
            html += '<span class="hs-note-date">' + new Date(note.date_created).toLocaleDateString() + '</span>';
            html += '</div>';
        }

        html += '<p class="hs-note-text">' + escapeHtml(note.note_text) + '</p>';

        if (isUserNote) {
            html += '<div class="hs-note-actions">';
            html += '<button class="hs-edit-note-btn" data-note-id="' + note.id + '">Edit</button>';
            html += '<span class="hs-note-visibility">' + (note.is_public ? '🌐 Public' : '🔒 Private') + '</span>';
            html += '</div>';
        }

        html += '</div>';
        return html;
    }

    /**
     * Edit an existing note
     */
    function editNote(noteId) {
        $.ajax({
            url: hsNotesModal.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hs_get_note',
                note_id: noteId,
                nonce: hsNotesModal.nonce
            },
            success: function(response) {
                if (response.success) {
                    const note = response.data;
                    $('#hs-note-id').val(note.id);
                    $('#hs-note-text').val(note.note_text);
                    $('#hs-note-page').val(note.page_number || '');
                    $('#hs-note-public').prop('checked', note.is_public == 1);
                    $('#hs-note-submit').text('Update Note');
                    $('#hs-note-delete').show();

                    switchTab('add-note');
                } else {
                    alert('Error loading note: ' + response.data);
                }
            },
            error: function() {
                alert('Error loading note. Please try again.');
            }
        });
    }

    /**
     * Switch between tabs
     */
    function switchTab(tabName) {
        // Hide all tabs
        $('.hs-tab-content').removeClass('active').hide();
        tabButtons.removeClass('active');

        // Show selected tab
        $('#' + tabName).addClass('active').show();
        $('[data-tab="' + tabName + '"]').addClass('active');
    }

    /**
     * Escape HTML special characters
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Event Listeners
     */

    // Close modal when X is clicked
    closeBtn.on('click', closeNotesModal);

    // Close modal when clicking outside of it
    $(window).on('click', function(event) {
        if (event.target == modal[0]) {
            closeNotesModal();
        }
    });

    // Tab switching
    tabButtons.on('click', function() {
        const tabName = $(this).data('tab');
        switchTab(tabName);
    });

    // Form submission
    noteForm.on('submit', function(e) {
        e.preventDefault();
        saveNote();
    });

    // Delete note button
    $('#hs-note-delete').on('click', function() {
        if (confirm('Are you sure you want to delete this note?')) {
            deleteNote();
        }
    });

    /**
     * Save note (create or update)
     */
    function saveNote() {
        const noteId = $('#hs-note-id').val();
        const bookId = $('#hs-note-book-id').val();
        const noteText = $('#hs-note-text').val();
        const pageNumber = $('#hs-note-page').val() || null;
        const isPublic = $('#hs-note-public').is(':checked') ? 1 : 0;

        const action = noteId ? 'hs_update_note' : 'hs_create_note';

        const data = {
            action: action,
            book_id: bookId,
            note_text: noteText,
            page_number: pageNumber,
            is_public: isPublic,
            nonce: hsNotesModal.nonce
        };

        if (noteId) {
            data.note_id = noteId;
        }

        $.ajax({
            url: hsNotesModal.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    alert('Note saved successfully!');
                    noteForm[0].reset();
                    $('#hs-note-id').val('');
                    $('#hs-note-submit').text('Save Note');
                    $('#hs-note-delete').hide();

                    // Reload notes
                    loadUserNotes(bookId);
                    switchTab('my-notes');
                } else {
                    alert('Error saving note: ' + response.data);
                }
            },
            error: function() {
                alert('Error saving note. Please try again.');
            }
        });
    }

    /**
     * Delete note
     */
    function deleteNote() {
        const noteId = $('#hs-note-id').val();
        const bookId = $('#hs-note-book-id').val();

        $.ajax({
            url: hsNotesModal.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hs_delete_note',
                note_id: noteId,
                nonce: hsNotesModal.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Note deleted successfully!');
                    noteForm[0].reset();
                    $('#hs-note-id').val('');
                    $('#hs-note-submit').text('Save Note');
                    $('#hs-note-delete').hide();

                    // Reload notes
                    loadUserNotes(bookId);
                    switchTab('my-notes');
                } else {
                    alert('Error deleting note: ' + response.data);
                }
            },
            error: function() {
                alert('Error deleting note. Please try again.');
            }
        });
    }

    // Trigger modal opening on notes button click
    $(document).on('click', '.hs-notes-button', function() {
        openNotesModal($(this));
    });
});
