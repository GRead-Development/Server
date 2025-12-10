// The main JavaScript file for HotSoup's book tracking

jQuery(document).ready(function($) {

    // Handles adding books to users' libraries
    $(document).on('click', '.hs-add-book', function(e) {
        e.preventDefault();
        const button = $(this);
        const bookID = button.data('book-id');

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_add_book',
                nonce: hs_ajax.nonce,
                book_id: bookID,
            },
            beforeSend: function() {
                button.text('Adding...').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Update the button to show it's been added successfully
                    button.text('Added').css('background-color', '#28a745').prop('disabled', true);
                } else {
                    button.text(response.data.message || 'Error').css('background-color', '#dc3545');
                }
            },
            error: function() {
                button.text('Request Failed').prop('disabled', false).css('background-color', '#dc3545');
            }
        });
    }); // <-- End of hs-add-book

    // Handles removing books from a user's library
    $(document).on('click', '.hs-remove-book', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure that you want to remove this book from your library?')) {
            return;
        }

        const button = $(this);
        const book_id = button.data('book-id');
        // Support both old list items and new card structure
        const list_item = button.closest('li[data-list-book-id="' + book_id + '"], .hs-book-card[data-list-book-id="' + book_id + '"]');

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_remove_book',
                nonce: hs_ajax.nonce,
                book_id: book_id,
            },
            success: function(response) {
                if (response.success) {
                    list_item.fadeOut(400, function() {
                        $(this).remove();
                    });
                } else {
                    alert('ERROR: ' + response.data.message);
                }
            },
            error: function() {
                alert('BIG PROBLEMO!');
            }
        });
    }); // <-- End of hs-remove-book

    // Handles actually updating progress
    $(document).on('submit', '.hs-progress-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const feedbackSpan = form.find('.hs-feedback');
        const progressbar = form.siblings('.hs-progress-bar-container').find('.hs-progress-bar');
        const progresstext = form.siblings('span');
        const booklist_item = form.closest('.hs-my-book');

	const new_page_val = parseInt(form.find('input[name="current_page"]').val());
	const max_pages = parseInt(form.find('input[name="current_page"]').attr('max'));

	const is_currently_completed = booklist_item.hasClass('completed');
	const will_be_incomplete = new_page_val < max_pages;
	const has_review = booklist_item.data('reviewed') === true || booklist_item.data('reviewed') === 'true';

	if (is_currently_completed && will_be_incomplete && has_review)
	{
		if (!confirm('WARNING: YOU ARE ABOUT TO MARK THIS BOOK AS "INCOMPLETE" THIS WILL PERMANENTLY DELETE YOUR REVIEW/RATING FOR THIS BOOK. YOU WILL LOSE THE POINTS YOU EARNED FROM YOUR REVIEW/RATING. THIS CANNOT BE REVERSED BY THE ADMINISTRATORS. ARE YOU SURE YOU WOULD LIKE TO CONTINUE?'))
		{
			// coward
			return;
		}
	}

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_update_progress',
                nonce: hs_ajax.nonce,
                book_id: form.find('input[name="book_id"]').val(),
                current_page: form.find('input[name="current_page"]').val(),
            },
            beforeSend: function() {
                feedbackSpan.text('Saving').css('color', '#666');
            },
            success: function(response) {
                if (response.success) {
                    progressbar.css('width', response.data.progress_percent + '%');
                    progresstext.text(response.data.progress_html);

                    if (response.data.completed) {
                        feedbackSpan.text('Book Completed!').css('color', '#D4AF37');
                        progressbar.addClass('golden');
                        progressbar.addClass('completed');
                        booklist_item.addClass('hs-book-achievement');

			// Show review button if possible
			booklist_item.find('.hs-review-section').slideDown();

                        setTimeout(function() {
                            booklist_item.removeClass('hs-book-achievement');
                        }, 1000);
                    } else {
                        feedbackSpan.text('Saved.').css('color', 'green');
                        progressbar.removeClass('golden');
                        booklist_item.removeClass('completed');


			// Handle review deletion (client-side)
			if (response.data.review_deleted)
			{
				booklist_item.data('reviewed', 'false');
				const review_section = booklist_item.find('.hs-review-section');
				review_section.find('.hs-toggle-review-form').text('Review Book');
				review_section.find('.hs-user-rating-display').empty();
				review_section.find('.hs-review-form').slideUp();
				review_section.find('input[name="hs_rating"]').val('');
				review_section.find('textarea[name="hs_review_text"]').val('');
			}

			booklist_item.find('.hs-review-section').slideUp();
                    }
                } else {
                    feedbackSpan.text(response.data.message).css('color', 'red');
                }
                setTimeout(() => feedbackSpan.text(''), 2000);
            },
            error: function() {
                feedbackSpan.text('Yikes!').css('color', 'red');
            }
        });
    }); // <-- End of hs-progress-form

	// Toggle review form
	$(document).on('click', '.hs-toggle-review-form', function(e)
	{
		e.preventDefault();
		$(this).siblings('.hs-review-form').slideToggle();
	});


	// Handles review form submission
	$(document).on('submit', '.hs-review-form', function(e)
	{
		e.preventDefault();
		const form = $(this);
		const feedback_span = form.find('.hs-review-feedback');
		const list_item = form.closest('.hs-my-book, .hs-book-card');
		const rating_display = list_item.find('.hs-user-rating-display');
		const toggle_button = list_item.find('.hs-toggle-review-form');

		const rating_val = form.find('input[name="hs_rating"]').val();

		// Client-side validation
		if (rating_val && (parseFloat(rating_val) < 0.0 || parseFloat(rating_val) > 10.0))
		{
			feedback_span.text('Your rating must be between 0.0 and 10.0.').css('color', 'red');
			return;
		}

		$.ajax({
			url: hs_ajax.ajax_url,
			type: 'POST',
			data:
			{
				action: 'hs_submit_review',
				nonce: hs_ajax.nonce,
				book_id: form.find('input[name="book_id"]').val(),
				rating: rating_val,
				review_text: form.find('textarea[name="hs_review_text"]').val()
			},

			beforeSend: function()
			{
				feedback_span.text('Saving...').css('color', '#666');
			},

			success: function(response)
			{
				if (response.success)
				{
					feedback_span.text(response.data.message).css('color', 'green');
					list_item.data('reviewed', 'true');
					toggle_button.text('Edit Review');
					rating_display.html(response.data.new_rating_html);

					setTimeout(() =>
					{
						feedback_span.text('');
						form.slideUp();
					}, 2000);
				}

				else
				{
					feedback_span.text(response.data.message).css('color', 'red');
				}
			},

			error: function()
			{
				feedback_span.text('Oops! Something is broken!').css('color', 'red');
			}
		});
	});


    // --- Report Inaccuracy Modal ---
    const report_modal = $('#hs-report-modal');
    const close_btn = $('#hs-close-report-modal');
    const submit_btn = $('#hs-submit-report-button');

    // Use event delegation for the open button
    $(document).on('click', '#hs-open-report-modal', function() {
        report_modal.show();
    });

    // Close button
    if (close_btn.length) {
        close_btn.on('click', function() {
            report_modal.hide();
        });
    }

    // Click outside to close
    $(window).on('click', function(e) {
        if ($(e.target).is(report_modal)) {
            report_modal.hide();
        }
    });

    // Submit report
    if (submit_btn.length) {
        submit_btn.on('click', function() {
            const button = $(this);
            const feedback_div = $('#hs-report-feedback');

            $.ajax({
                url: hs_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'hs_submit_report',
                    nonce: hs_ajax.nonce,
                    book_id: button.data('book-id'),
                    report_text: $('#hs-report-textarea').val()
                },
                beforeSend: function() {
                    button.text('Submitting report...').prop('disabled', true);
                    feedback_div.text('');
                },
                success: function(response) {
                    if (response.success) {
                        feedback_div.text(response.data.message).css('color', 'green');
                        setTimeout(() => {
                            report_modal.hide();
                            button.text('Submit Report').prop('disabled', false);
                            $('#hs-report-textarea').val('');
                            feedback_div.text('');
                        }, 2000);
                    } else {
                        feedback_div.text(response.data.message).css('color', 'red');
                        button.text('Submit Report').prop('disabled', false);
                    }
                },
                error: function() {
                    feedback_div.text('Oops! Error!').css('color', 'red');
                    button.text('Submit Report').prop('disabled', false);
                }
            });
        });
    } // <-- End of submit_btn.length

// --- NEW: User Moderation ---

    // Block User
    $(document).on('click', '.hs-block-user', function(e) {
        e.preventDefault();
        const button = $(this);
        const userId = button.data('user-id');
        const feedbackSpan = button.siblings('.hs-moderation-feedback');

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_block_user',
                nonce: hs_ajax.nonce,
                target_user_id: userId,
            },
            beforeSend: function() {
                button.text('Blocking...').prop('disabled', true);
                feedbackSpan.text('');
            },
            success: function(response) {
                if (response.success) {
                    feedbackSpan.text(response.data.message).css('color', 'green');
                    button.text('Unblock User').removeClass('hs-block-user').addClass('hs-unblock-user');
                } else {
                    feedbackSpan.text(response.data.message).css('color', 'red');
                }
                button.prop('disabled', false);
            },
            error: function() {
                feedbackSpan.text('Request Failed.').css('color', 'red');
                button.text('Block User').prop('disabled', false);
            }
        });
    });

    // Unblock User
    $(document).on('click', '.hs-unblock-user', function(e) {
        e.preventDefault();
        const button = $(this);
        const userId = button.data('user-id');
        const feedbackSpan = button.siblings('.hs-moderation-feedback');

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_unblock_user',
                nonce: hs_ajax.nonce,
                target_user_id: userId,
            },
            beforeSend: function() {
                button.text('Unblocking...').prop('disabled', true);
                feedbackSpan.text('');
            },
            success: function(response) {
                if (response.success) {
                    feedbackSpan.text(response.data.message).css('color', 'green');
                    button.text('Block User').removeClass('hs-unblock-user').addClass('hs-block-user');
                } else {
                    feedbackSpan.text(response.data.message).css('color', 'red');
                }
                button.prop('disabled', false);
            },
            error: function() {
                feedbackSpan.text('Request Failed.').css('color', 'red');
                button.text('Unblock User').prop('disabled', false);
            }
        });
    });

    // --- Report User Modal ---
    const reportUserModal = $('#hs-report-user-modal');
    const closeUserReportBtn = $('#hs-close-report-user-modal');

    // Open report user modal
    $(document).on('click', '.hs-report-user-modal-open', function() {
        reportUserModal.show();
    });

    // Close button
    if (closeUserReportBtn.length) {
        closeUserReportBtn.on('click', function() {
            reportUserModal.hide();
        });
    }

    // Click outside to close
    $(window).on('click', function(e) {
        if ($(e.target).is(reportUserModal)) {
            reportUserModal.hide();
        }
    });

    // Submit User Report
    $(document).on('submit', '#hs-report-user-form', function(e) {
        e.preventDefault();
        const button = $('#hs-submit-user-report-button');
        const feedback_div = $('#hs-report-user-feedback');
        const target_user_id = $('#hs-report-user-id').val();
        const reason = $('#hs-report-user-textarea').val();

        if (reason.trim() === '') {
            feedback_div.text('Please provide a reason.').css('color', 'red');
            return;
        }

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_report_user',
                nonce: hs_ajax.nonce,
                target_user_id: target_user_id,
                reason: reason
            },
            beforeSend: function() {
                button.text('Submitting...').prop('disabled', true);
                feedback_div.text('');
            },
            success: function(response) {
                if (response.success) {
                    feedback_div.text(response.data.message).css('color', 'green');
                    setTimeout(() => {
                        reportUserModal.hide();
                        button.text('Submit Report').prop('disabled', false);
                        $('#hs-report-user-textarea').val('');
                        feedback_div.text('');
                    }, 2000);
                } else {
                    feedback_div.text(response.data.message).css('color', 'red');
                    button.text('Submit Report').prop('disabled', false);
                }
            },
            error: function() {
                feedback_div.text('Oops! An error occurred!').css('color', 'red');
                button.text('Submit Report').prop('disabled', false);
            }
        });
    });

    // --- DNF (Did Not Finish) Functionality ---
    const dnfModal = $('#hs-dnf-modal');
    const closeDnfBtn = $('#hs-close-dnf-modal');
    let currentDnfBookId = null;

    // Open DNF modal
    $(document).on('click', '.hs-dnf-book', function(e) {
        e.preventDefault();
        currentDnfBookId = $(this).data('book-id');
        $('#hs-dnf-book-id').val(currentDnfBookId);
        $('#hs-dnf-reason-textarea').val('');
        $('#hs-dnf-feedback').text('');
        dnfModal.show();
    });

    // Close DNF modal
    if (closeDnfBtn.length) {
        closeDnfBtn.on('click', function() {
            dnfModal.hide();
        });
    }

    // Click outside to close
    $(window).on('click', function(e) {
        if ($(e.target).is(dnfModal)) {
            dnfModal.hide();
        }
    });

    // Submit DNF
    $(document).on('submit', '#hs-dnf-form', function(e) {
        e.preventDefault();
        const button = $('#hs-submit-dnf-button');
        const feedback_div = $('#hs-dnf-feedback');
        const book_id = $('#hs-dnf-book-id').val();
        const reason = $('#hs-dnf-reason-textarea').val();
        const list_item = $('.hs-my-book[data-list-book-id="' + book_id + '"]');

        if (reason.trim() === '') {
            feedback_div.text('Please provide a reason.').css('color', 'red');
            return;
        }

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_mark_book_dnf',
                nonce: hs_ajax.nonce,
                book_id: book_id,
                reason: reason
            },
            beforeSend: function() {
                button.text('Saving...').prop('disabled', true);
                feedback_div.text('');
            },
            success: function(response) {
                if (response.success) {
                    feedback_div.text('Book marked as DNF!').css('color', 'green');

                    setTimeout(() => {
                        dnfModal.hide();
                        button.text('Mark as DNF').prop('disabled', false);

                        // Update the book item status
                        list_item.addClass('hs-book-dnf');
                        list_item.find('.hs-book-status-badge').remove();
                        list_item.find('h3').after('<span class="hs-book-status-badge hs-badge-dnf">DNF</span>');
                        list_item.find('.hs-dnf-book').replaceWith('<span class="hs-dnf-reason-display">DNF Reason: ' + reason + '</span>');

                        // Move to a separate section or mark visually
                        list_item.fadeOut(400, function() {
                            // Could move to a separate DNF section if desired
                            $(this).appendTo('#hs-dnf-books-list').fadeIn(400);
                        });
                    }, 2000);
                } else {
                    feedback_div.text(response.data.message).css('color', 'red');
                    button.text('Mark as DNF').prop('disabled', false);
                }
            },
            error: function() {
                feedback_div.text('Request failed!').css('color', 'red');
                button.text('Mark as DNF').prop('disabled', false);
            }
        });
    });

    // --- Pause Book ---
    $(document).on('click', '.hs-pause-book', function(e) {
        e.preventDefault();
        const button = $(this);
        const book_id = button.data('book-id');
        const list_item = button.closest('.hs-my-book');
        const feedback_span = list_item.find('.hs-book-action-feedback');

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_pause_book',
                nonce: hs_ajax.nonce,
                book_id: book_id
            },
            beforeSend: function() {
                button.text('Pausing...').prop('disabled', true);
                feedback_span.text('');
            },
            success: function(response) {
                if (response.success) {
                    feedback_span.text('Book paused!').css('color', 'green');

                    // Update UI
                    list_item.addClass('hs-book-paused');
                    list_item.find('.hs-book-status-badge').remove();
                    list_item.find('h3').after('<span class="hs-book-status-badge hs-badge-paused">Paused</span>');
                    button.text('Resume').removeClass('hs-pause-book').addClass('hs-resume-book').prop('disabled', false);

                    setTimeout(() => feedback_span.text(''), 2000);
                } else {
                    feedback_span.text(response.data.message).css('color', 'red');
                    button.text('Pause').prop('disabled', false);
                }
            },
            error: function() {
                feedback_span.text('Request failed!').css('color', 'red');
                button.text('Pause').prop('disabled', false);
            }
        });
    });

    // --- Resume Book ---
    $(document).on('click', '.hs-resume-book', function(e) {
        e.preventDefault();
        const button = $(this);
        const book_id = button.data('book-id');
        const list_item = button.closest('.hs-my-book');
        const feedback_span = list_item.find('.hs-book-action-feedback');

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_resume_book',
                nonce: hs_ajax.nonce,
                book_id: book_id
            },
            beforeSend: function() {
                button.text('Resuming...').prop('disabled', true);
                feedback_span.text('');
            },
            success: function(response) {
                if (response.success) {
                    feedback_span.text('Book resumed!').css('color', 'green');

                    // Update UI
                    list_item.removeClass('hs-book-paused');
                    list_item.find('.hs-book-status-badge').remove();
                    button.text('Pause').removeClass('hs-resume-book').addClass('hs-pause-book').prop('disabled', false);

                    setTimeout(() => feedback_span.text(''), 2000);
                } else {
                    feedback_span.text(response.data.message).css('color', 'red');
                    button.text('Resume').prop('disabled', false);
                }
            },
            error: function() {
                feedback_span.text('Request failed!').css('color', 'red');
                button.text('Resume').prop('disabled', false);
            }
        });
    });

    // --- Toggle Update Progress Form ---
    $(document).on('click', '.hs-toggle-progress', function(e) {
        e.preventDefault();
        const button = $(this);
        const bookId = button.data('book-id');
        const card = button.closest('.hs-book-card');
        const progressForm = card.find('.hs-progress-form');

        // Toggle the form
        progressForm.slideToggle(200);

        // Update button text
        if (progressForm.is(':visible')) {
            button.text('Hide Progress Form');
        } else {
            button.text('Update Progress');
        }
    });

    // --- Toggle More Menu ---
    $(document).on('click', '.hs-toggle-more-menu', function(e) {
        e.preventDefault();
        const button = $(this);
        const bookId = button.data('book-id');
        const card = button.closest('.hs-book-card');
        const moreMenu = card.find('.hs-more-menu');

        // Toggle the menu
        moreMenu.slideToggle(200);

        // Update button text
        if (moreMenu.is(':visible')) {
            button.text('Less...');
        } else {
            button.text('More...');
        }
    });

    // --- Book Cover Fetching from OpenLibrary ---
    // This fetches book covers client-side (using user's IP) and caches them locally
    function fetchBookCovers() {
        // Fetch covers for library cards
        $('.hs-book-card').each(function() {
            const card = $(this);
            const isbn = card.data('isbn');
            const bookId = card.data('list-book-id');
            const coverDiv = card.find('.hs-book-cover');

            // Skip if no ISBN or cover already loaded
            if (!isbn || isbn === '') {
                return;
            }

            // Check if cover already has an image
            const currentBg = coverDiv.css('background-image');
            if (currentBg && currentBg !== 'none' && currentBg !== 'url()' && !currentBg.includes('url()')) {
                return; // Cover already loaded
            }

            // Add loading class
            coverDiv.addClass('loading');

            // Fetch from OpenLibrary API (client-side request using user's IP)
            const openLibraryUrl = `https://covers.openlibrary.org/b/isbn/${isbn}-L.jpg`;

            // Create an image to test if the cover exists
            const testImg = new Image();
            testImg.onload = function() {
                // Cover exists! Display it immediately
                coverDiv.css('background-image', `url(${openLibraryUrl})`);
                coverDiv.removeClass('loading');

                // Cache it on the server in the background
                $.ajax({
                    url: hs_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'hs_cache_book_cover',
                        nonce: hs_ajax.nonce,
                        book_id: bookId,
                        cover_url: openLibraryUrl
                    },
                    success: function(response) {
                        if (response.success && response.data.cover_url) {
                            // Update to use locally cached version
                            coverDiv.css('background-image', `url(${response.data.cover_url})`);
                        }
                    }
                    // Silent fail - cover is already displayed from OpenLibrary
                });
            };

            testImg.onerror = function() {
                // No cover found, keep default gradient
                coverDiv.removeClass('loading');
            };

            testImg.src = openLibraryUrl;
        });
    }

    // Run on page load
    fetchBookCovers();

    // Also run after any AJAX updates that might add new books
    $(document).on('hs-library-updated', function() {
        fetchBookCovers();
    });

    // --- Fetch cover for book page ---
    function fetchBookPageCover() {
        const coverDiv = $('.hs-book-page-cover-img.no-cover');
        if (coverDiv.length === 0) return;

        const isbn = coverDiv.data('isbn');
        if (!isbn || isbn === '') return;

        coverDiv.addClass('loading');

        const openLibraryUrl = `https://covers.openlibrary.org/b/isbn/${isbn}-L.jpg`;

        const testImg = new Image();
        testImg.onload = function() {
            coverDiv.css('background-image', `url(${openLibraryUrl})`);
            coverDiv.removeClass('loading no-cover');
        };

        testImg.onerror = function() {
            coverDiv.removeClass('loading');
        };

        testImg.src = openLibraryUrl;
    }

    // Run on book pages
    fetchBookPageCover();

    // --- Activity Feed Filter Menu ---
    function initActivityFilterMenu() {
        // Don't create duplicate filter menus
        if ($('.hs-activity-filter-wrapper').length > 0) {
            console.log('Filter wrapper already exists');
            return;
        }

        // Find the activity navigation (BuddyX theme uses these classes)
        const filterTabs = $('.activity-type-navs');

        console.log('Filter tabs found:', filterTabs.length);

        if (filterTabs.length === 0) {
            console.log('No filter tabs found');
            return;
        }

        console.log('Creating filter menu...');

        // Create wrapper for our custom filter menu
        const filterWrapper = $('<div class="hs-activity-filter-wrapper"></div>');

        // Create toggle button
        const toggleButton = $('<button class="hs-activity-filter-toggle">Filter Posts <span class="arrow">▼</span></button>');

        // Create content container
        const filterContent = $('<div class="hs-activity-filter-content"></div>');

        // Clone the filter tabs into our content container
        const filterList = filterTabs.clone();
        filterList.show(); // Make sure it's visible in our container
        filterContent.append(filterList);

        // Assemble the wrapper
        filterWrapper.append(toggleButton);
        filterWrapper.append(filterContent);

        // Insert before the activity stream
        if ($('#activity-stream').length > 0) {
            $('#activity-stream').before(filterWrapper);
            console.log('Inserted before activity-stream');
        } else {
            filterTabs.before(filterWrapper);
            console.log('Inserted before filter tabs');
        }

        console.log('Filter menu created successfully');

        // Debug: Check if button is actually in the DOM and visible
        setTimeout(function() {
            const buttonCheck = $('.hs-activity-filter-toggle');
            console.log('Button exists in DOM:', buttonCheck.length);
            if (buttonCheck.length > 0) {
                console.log('Button position:', buttonCheck.offset());
                console.log('Button is visible:', buttonCheck.is(':visible'));
                console.log('Button display style:', buttonCheck.css('display'));
                console.log('Button parent:', buttonCheck.parent().attr('class'));
            }
        }, 100);

        // Toggle functionality
        toggleButton.on('click', function(e) {
            e.preventDefault();
            console.log('Toggle button clicked');
            $(this).toggleClass('active');
            filterContent.toggleClass('show');
        });
    }

    // Run on activity pages with delay to ensure DOM is ready
    setTimeout(function() {
        initActivityFilterMenu();
    }, 500);

    // Also try immediately
    initActivityFilterMenu();

    // Re-run after AJAX updates (BuddyPress uses AJAX for activity filtering)
    $(document).ajaxComplete(function() {
        setTimeout(initActivityFilterMenu, 100);
    });

}); // <-- This is the one, final closing bracket for jQuery(document).ready()
