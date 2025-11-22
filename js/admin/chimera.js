jQuery(document).ready(function($) {
	// Store current field being edited for API search
	let currentSearchField = null;
	let currentSearchBookId = null;
	let currentSearchFieldType = null;
	let googleBooksApiKey = hsBookAuditor.googleBooksApiKey || null;

	// Handle select all checkboxes
	$(document).on('change', '#hs-select-all-books', function() {
		const isChecked = $(this).is(':checked');
		$('.hs-book-select-checkbox').prop('checked', isChecked);
		updateBulkActionsToolbar();
	});

	// Handle individual checkboxes
	$(document).on('change', '.hs-book-select-checkbox', function() {
		updateBulkActionsToolbar();
	});

	// Update bulk actions toolbar visibility
	function updateBulkActionsToolbar() {
		const checkedCount = $('.hs-book-select-checkbox:checked').length;
		const $toolbar = $('#hs-bulk-actions-toolbar');

		if (checkedCount > 0) {
			$toolbar.show();
			$('#hs-bulk-selection-count').text(`${checkedCount} book${checkedCount !== 1 ? 's' : ''} selected`);

			// Update select all checkbox state
			const totalCheckboxes = $('.hs-book-select-checkbox').length;
			$('#hs-select-all-books').prop('checked', checkedCount === totalCheckboxes);
		} else {
			$toolbar.hide();
			$('#hs-select-all-books').prop('checked', false);
		}
	}

	// Handle bulk action button
	$(document).on('click', '#hs-bulk-action-btn', function(e) {
		e.preventDefault();

		const action = $('#hs-bulk-action-select').val();
		if (!action) {
			alert('Please select an action');
			return;
		}

		const selectedBookIds = [];
		$('.hs-book-select-checkbox:checked').each(function() {
			selectedBookIds.push($(this).val());
		});

		if (selectedBookIds.length === 0) {
			alert('Please select at least one book');
			return;
		}

		// Show progress
		$('#hs-bulk-progress').show();
		$('#hs-bulk-progress-bar').attr('max', selectedBookIds.length).val(0);
		$('#hs-bulk-progress-text').text(`0 / ${selectedBookIds.length}`);
		$('#hs-bulk-action-btn').prop('disabled', true);
		$('#hs-bulk-action-select').prop('disabled', true);

		// Determine the field to populate based on action
		let fieldToPopulate = '';
		switch (action) {
			case 'fill_titles':
				fieldToPopulate = 'post_title';
				break;
			case 'fill_authors':
				fieldToPopulate = 'book_author';
				break;
			case 'fill_isbns':
				fieldToPopulate = 'book_isbn';
				break;
			case 'fill_pages':
				fieldToPopulate = 'nop';
				break;
		}

		processBulkBooks(selectedBookIds, fieldToPopulate, 0);
	});

	// Process bulk books one by one
	function processBulkBooks(bookIds, field, index) {
		if (index >= bookIds.length) {
			// All done
			$('#hs-bulk-progress-bar').val(bookIds.length);
			$('#hs-bulk-progress-text').text(`${bookIds.length} / ${bookIds.length} ✓`);
			$('#hs-bulk-action-btn').prop('disabled', false);
			$('#hs-bulk-action-select').prop('disabled', false);

			setTimeout(function() {
				$('#hs-bulk-progress').hide();
				$('#hs-bulk-action-select').val('');
				location.reload();
			}, 2000);
			return;
		}

		const bookId = bookIds[index];
		const $row = $(`tr[data-book-id="${bookId}"]`);
		const $isbnField = $row.find('[data-field="book_isbn"]');
		const isbn = $isbnField.find('.hs-field-input').val() || $isbnField.find('.hs-field-display').text();

		// Skip if no ISBN
		if (!isbn || isbn === '—') {
			$('#hs-bulk-progress-bar').val(index + 1);
			$('#hs-bulk-progress-text').text(`${index + 1} / ${bookIds.length} (skipped - no ISBN)`);
			setTimeout(function() {
				processBulkBooks(bookIds, field, index + 1);
			}, 300);
			return;
		}

		// Fetch from Google Books API
		$.ajax({
			url: 'https://www.googleapis.com/books/v1/volumes',
			data: {
				q: 'isbn:' + isbn,
				maxResults: 1,
				key: googleBooksApiKey,
			},
			dataType: 'json',
			timeout: 5000,
			success: function(response) {
				if (response.items && response.items.length > 0) {
					const item = response.items[0];
					const info = item.volumeInfo || {};
					const $fieldInput = $row.find(`[data-field="${field}"] .hs-field-input`);

					let populatedValue = '';

					switch (field) {
						case 'post_title':
							populatedValue = info.title || '';
							break;
						case 'book_author':
							populatedValue = info.authors ? info.authors[0] : '';
							break;
						case 'book_isbn':
							populatedValue = getIsbnFromItem(info);
							populatedValue = populatedValue !== 'N/A' ? populatedValue : '';
							break;
						case 'nop':
							populatedValue = info.pageCount || '';
							break;
					}

					if (populatedValue) {
						// Save via AJAX
						$.ajax({
							type: 'POST',
							url: hsBookAuditor.ajaxurl,
							data: {
								action: 'hs_save_book_field',
								nonce: hsBookAuditor.nonce,
								book_id: bookId,
								field: field,
								value: populatedValue,
							},
							success: function(response) {
								$('#hs-bulk-progress-bar').val(index + 1);
								$('#hs-bulk-progress-text').text(`${index + 1} / ${bookIds.length}`);
								setTimeout(function() {
									processBulkBooks(bookIds, field, index + 1);
								}, 300);
							},
							error: function() {
								$('#hs-bulk-progress-bar').val(index + 1);
								$('#hs-bulk-progress-text').text(`${index + 1} / ${bookIds.length} (error)`);
								setTimeout(function() {
									processBulkBooks(bookIds, field, index + 1);
								}, 300);
							},
						});
					} else {
						$('#hs-bulk-progress-bar').val(index + 1);
						$('#hs-bulk-progress-text').text(`${index + 1} / ${bookIds.length} (no data)`);
						setTimeout(function() {
							processBulkBooks(bookIds, field, index + 1);
						}, 300);
					}
				} else {
					$('#hs-bulk-progress-bar').val(index + 1);
					$('#hs-bulk-progress-text').text(`${index + 1} / ${bookIds.length} (no match)`);
					setTimeout(function() {
						processBulkBooks(bookIds, field, index + 1);
					}, 300);
				}
			},
			error: function() {
				$('#hs-bulk-progress-bar').val(index + 1);
				$('#hs-bulk-progress-text').text(`${index + 1} / ${bookIds.length} (api error)`);
				setTimeout(function() {
					processBulkBooks(bookIds, field, index + 1);
				}, 300);
			},
		});
	}

	// Handle clear selection button
	$(document).on('click', '#hs-bulk-clear-selection-btn', function(e) {
		e.preventDefault();
		$('.hs-book-select-checkbox').prop('checked', false);
		$('#hs-select-all-books').prop('checked', false);
		updateBulkActionsToolbar();
	});
	// Handle clicking on editable fields to enter edit mode
	$(document).on('click', '.hs-editable-field .hs-field-display', function(e) {
		// Check if they clicked the [view] link - if so, let it through
		if (e.target.classList.contains('hs-view-link')) {
			return;
		}

		e.preventDefault();
		e.stopPropagation();

		const $field = $(this).closest('.hs-editable-field');
		const $display = $field.find('.hs-field-display');
		const $input = $field.find('.hs-field-input');
		const $actions = $field.find('.hs-field-actions');

		// Switch to edit mode
		$display.hide();
		$input.show().focus();
		$actions.show();
	});

	// Handle canceling field edits
	$(document).on('click', '.hs-editable-field .hs-cancel-field', function(e) {
		e.preventDefault();

		const $field = $(this).closest('.hs-editable-field');
		const $display = $field.find('.hs-field-display');
		const $input = $field.find('.hs-field-input');
		const $actions = $field.find('.hs-field-actions');

		// Switch back to display mode
		$display.show();
		$input.hide();
		$actions.hide();
	});

	// Handle saving field edits
	$(document).on('click', '.hs-editable-field .hs-save-field', function(e) {
		e.preventDefault();

		const $field = $(this).closest('.hs-editable-field');
		const $display = $field.find('.hs-field-display');
		const $input = $field.find('.hs-field-input');
		const $actions = $field.find('.hs-field-actions');
		const $button = $(this);

		const bookId = $field.data('book-id');
		const fieldName = $field.data('field');
		const fieldValue = $input.val();

		// Disable button while saving
		$button.prop('disabled', true);

		// Make AJAX request to save the field
		$.ajax({
			type: 'POST',
			url: hsBookAuditor.ajaxurl,
			data: {
				action: 'hs_save_book_field',
				nonce: hsBookAuditor.nonce,
				book_id: bookId,
				field: fieldName,
				value: fieldValue,
			},
			success: function(response) {
				if (response.success) {
					// Update the display text
					const displayText = fieldValue || (fieldName === 'book_isbn' || fieldName === 'nop' ? '—' : 'Unknown');
					$display.text(displayText);

					// Switch back to display mode
					$display.show();
					$input.hide();
					$actions.hide();
					$button.prop('disabled', false);
				} else {
					// Show error
					alert('Error saving field: ' + (response.data.message || 'Unknown error'));
					$button.prop('disabled', false);
				}
			},
			error: function() {
				alert('Error saving field: Request failed');
				$button.prop('disabled', false);
			},
		});
	});

	// Handle marking a book as audited
	$('.hs-audit-book-btn').on('click', function(e) {
		e.preventDefault();

		const $button = $(this);
		const bookId = $button.data('book-id');
		const $row = $button.closest('.hs-book-row');
		const $spinner = $row.find('.hs-audit-spinner');
		const $feedback = $row.find('.hs-audit-feedback');

		// Disable button and show spinner
		$button.prop('disabled', true);
		$spinner.css('visibility', 'visible');
		$feedback.removeClass('error').text('');

		// Make AJAX request
		$.ajax({
			type: 'POST',
			url: hsBookAuditor.ajaxurl,
			data: {
				action: 'hs_mark_book_audited',
				nonce: hsBookAuditor.nonce,
				book_id: bookId,
			},
			success: function(response) {
				if (response.success) {
					// Show success feedback
					$feedback.removeClass('error').text('✓ Audited!');

					// Add success class and fade out the row
					$row.addClass('hs-audited');

					// Fade out the row after a short delay
					setTimeout(function() {
						$row.fadeOut(300, function() {
							$(this).remove();

							// Check if there are any remaining unaudited books
							if ($('.hs-book-row').length === 0) {
								location.reload();
							}
						});
					}, 500);
				} else {
					// Show error feedback
					$feedback.addClass('error').text('✗ ' + (response.data.message || 'Error'));
					$button.prop('disabled', false);
					$spinner.css('visibility', 'hidden');
				}
			},
			error: function() {
				// Show error feedback
				$feedback.addClass('error').text('✗ Request failed');
				$button.prop('disabled', false);
				$spinner.css('visibility', 'hidden');
			},
		});
	});

	// Handle API search button click
	$(document).on('click', '.hs-editable-field .hs-api-search-field', function(e) {
		e.preventDefault();

		const $field = $(this).closest('.hs-editable-field');
		currentSearchField = $field.data('field');
		currentSearchBookId = $field.data('book-id');
		currentSearchFieldType = $field.find('.hs-field-input').attr('type');

		// Get ISBN from the book row
		const $bookRow = $field.closest('.hs-book-row');
		const $isbnField = $bookRow.find('[data-field="book_isbn"]');
		const isbn = $isbnField.find('.hs-field-input').val() || $isbnField.find('.hs-field-display').text();

		if (!isbn || isbn === '—') {
			alert('Please enter an ISBN first');
			return;
		}

		// Show modal with loading message
		$('#hs-api-modal-title').text('Searching...');
		$('#hs-api-search-spinner').show();
		$('#hs-api-search-message').hide().empty().removeClass('success error');
		$('#hs-api-search-modal').show();

		// Auto-search using ISBN
		if (!googleBooksApiKey) {
			showApiMessage('Google Books API key not configured', 'error');
			return;
		}

		$.ajax({
			url: 'https://www.googleapis.com/books/v1/volumes',
			data: {
				q: 'isbn:' + isbn,
				maxResults: 1,
				key: googleBooksApiKey,
			},
			dataType: 'json',
			timeout: 5000,
			success: function(response) {
				$('#hs-api-search-spinner').hide();

				if (!response.items || response.items.length === 0) {
					showApiMessage('No results found for this ISBN', 'error');
					return;
				}

				// Auto-populate the clicked field with data from first result
				const item = response.items[0];
				const info = item.volumeInfo || {};
				const $field = $('[data-field="' + currentSearchField + '"][data-book-id="' + currentSearchBookId + '"]');
				const $input = $field.find('.hs-field-input');

				let populatedValue = '';

				switch (currentSearchField) {
					case 'post_title':
						populatedValue = info.title || '';
						break;
					case 'book_author':
						populatedValue = info.authors ? info.authors[0] : '';
						break;
					case 'book_isbn':
						const isbn = getIsbnFromItem(info);
						populatedValue = isbn !== 'N/A' ? isbn : '';
						break;
					case 'nop':
						populatedValue = info.pageCount || '';
						break;
				}

				if (populatedValue) {
					$input.val(populatedValue);
					$('#hs-api-modal-title').text('Success!');
					showApiMessage('Data loaded for ' + currentSearchField.replace('_', ' ') + '. Click Save to confirm.', 'success');
				} else {
					showApiMessage('No data found for this field', 'error');
				}

				// Close modal after 2 seconds
				setTimeout(function() {
					$('#hs-api-search-modal').hide();
				}, 2000);
			},
			error: function() {
				$('#hs-api-search-spinner').hide();
				showApiMessage('Failed to fetch data from Google Books API', 'error');
			},
		});
	});

	// Handle API search modal close
	$(document).on('click', '.hs-modal-close, #hs-api-search-modal', function(e) {
		if (e.target.id === 'hs-api-search-modal' || e.target.classList.contains('hs-modal-close')) {
			$('#hs-api-search-modal').hide();
		}
	});

	// Prevent closing modal when clicking inside modal content
	$(document).on('click', '.hs-modal-content', function(e) {
		e.stopPropagation();
	});


	// Extract ISBN from Google Books item
	function getIsbnFromItem(info) {
		if (!info.industryIdentifiers) return 'N/A';

		// Look for ISBN-13 first, then ISBN-10
		const isbn13 = info.industryIdentifiers.find(id => id.type === 'ISBN_13');
		if (isbn13) return isbn13.identifier;

		const isbn10 = info.industryIdentifiers.find(id => id.type === 'ISBN_10');
		if (isbn10) return isbn10.identifier;

		return 'N/A';
	}

	// Show API message
	function showApiMessage(message, type) {
		const $msg = $('#hs-api-search-message');
		$msg.text(message).removeClass('success error').addClass(type).show();
	}

	// Store the book ID being deleted
	let bookBeingDeleted = null;

	// Handle delete book button
	$(document).on('click', '.hs-delete-book-btn', function(e) {
		e.preventDefault();

		const $button = $(this);
		bookBeingDeleted = $button.data('book-id');

		// Show confirmation modal
		$('#hs-delete-confirm-modal').show();
	});

	// Handle close delete modal (X button and Cancel button)
	$(document).on('click', '.hs-delete-modal-close, #hs-delete-confirm-no, #hs-delete-confirm-modal', function(e) {
		if (e.target.id === 'hs-delete-confirm-modal' ||
		    e.target.id === 'hs-delete-confirm-no' ||
		    e.target.classList.contains('hs-delete-modal-close')) {
			$('#hs-delete-confirm-modal').hide();
			bookBeingDeleted = null;
		}
	});

	// Prevent closing modal when clicking inside modal content
	$(document).on('click', '#hs-delete-confirm-modal .hs-modal-content', function(e) {
		e.stopPropagation();
	});

	// Handle confirm delete button
	$(document).on('click', '#hs-delete-confirm-yes', function(e) {
		e.preventDefault();

		if (!bookBeingDeleted) return;

		const $row = $('[data-book-id="' + bookBeingDeleted + '"]').closest('.hs-book-row');
		const $button = $row.find('.hs-delete-book-btn');
		const $feedback = $row.find('.hs-audit-feedback');
		const $spinner = $row.find('.hs-audit-spinner');

		// Close modal
		$('#hs-delete-confirm-modal').hide();

		// Disable button and show spinner
		$button.prop('disabled', true);
		$spinner.css('visibility', 'visible');
		$feedback.removeClass('error').text('');

		// Make AJAX request to delete the book
		$.ajax({
			type: 'POST',
			url: hsBookAuditor.ajaxurl,
			data: {
				action: 'hs_delete_book',
				nonce: hsBookAuditor.nonce,
				book_id: bookBeingDeleted,
			},
			success: function(response) {
				if (response.success) {
					// Show success feedback
					$feedback.removeClass('error').text('✓ Deleted!');

					// Fade out the row
					$row.fadeOut(300, function() {
						$(this).remove();

						// Check if there are any remaining unaudited books
						if ($('.hs-book-row').length === 0) {
							location.reload();
						}
					});
				} else {
					// Show error feedback
					$feedback.addClass('error').text('✗ ' + (response.data.message || 'Error'));
					$button.prop('disabled', false);
					$spinner.css('visibility', 'hidden');
				}
				bookBeingDeleted = null;
			},
			error: function() {
				// Show error feedback
				$feedback.addClass('error').text('✗ Request failed');
				$button.prop('disabled', false);
				$spinner.css('visibility', 'hidden');
				bookBeingDeleted = null;
			},
		});
	});

	// Escape HTML to prevent XSS
	function escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;',
		};
		return text.replace(/[&<>"']/g, function(m) {
			return map[m];
		});
	}
});
