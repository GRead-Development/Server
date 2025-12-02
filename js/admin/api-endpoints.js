/**
 * API Endpoints Admin Panel JavaScript
 *
 * Handles UI interactions for the API endpoints admin panel
 *
 * @package HotSoup
 * @since 1.0.0
 */

(function($) {
    'use strict';

    let allEndpointsData = [];

    $(document).ready(function() {
        initializeEndpointsPanel();
    });

    /**
     * Initialize the endpoints panel
     */
    function initializeEndpointsPanel() {
        // Store initial endpoints data
        storeEndpointsData();

        // Bind event handlers
        $('#refresh-endpoints').on('click', refreshEndpoints);
        $('#export-json').on('click', exportAsJSON);
        $('#export-csv').on('click', exportAsCSV);
        $('#export-markdown').on('click', exportAsMarkdown);
        $('#search-endpoints').on('input', filterEndpoints);
        $('#filter-method').on('change', filterEndpoints);
        $('#filter-namespace').on('change', filterEndpoints);

        // View details buttons (delegated event for dynamic content)
        $(document).on('click', '.view-details', function() {
            const index = $(this).data('index');
            toggleDetails(index);
        });
    }

    /**
     * Store endpoints data from the DOM
     */
    function storeEndpointsData() {
        allEndpointsData = [];
        $('.endpoint-row').each(function() {
            const $row = $(this);
            const $detailsRow = $row.next('.endpoint-details');

            const endpoint = {
                method: $row.data('method'),
                namespace: $row.data('namespace'),
                route: $row.find('.column-route code').text(),
                description: $row.find('.column-description').text(),
                auth: $row.find('.column-auth span').text(),
                details: $detailsRow.find('.endpoint-details-content').html()
            };

            allEndpointsData.push(endpoint);
        });
    }

    /**
     * Refresh endpoints data from server
     */
    function refreshEndpoints() {
        const $button = $('#refresh-endpoints');
        const originalText = $button.html();

        // Show loading state
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Refreshing...');
        $('#loading-indicator').show();

        $.ajax({
            url: hotSoupApiEndpoints.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hotsoup_get_endpoints',
                nonce: hotSoupApiEndpoints.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update the table
                    $('#endpoints-container').html(response.data.html);

                    // Update stats
                    updateStats(response.data.stats);

                    // Re-store endpoints data
                    storeEndpointsData();

                    // Show success message
                    showNotice('Endpoints refreshed successfully!', 'success');
                } else {
                    showNotice('Failed to refresh endpoints: ' + (response.data.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotice('Failed to refresh endpoints: ' + error, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
                $('#loading-indicator').hide();
            }
        });
    }

    /**
     * Update statistics display
     */
    function updateStats(stats) {
        $('.stat-box').eq(0).find('.stat-number').text(stats.total);
        $('.stat-box').eq(1).find('.stat-number').text(stats.get);
        $('.stat-box').eq(2).find('.stat-number').text(stats.post);
        $('.stat-box').eq(3).find('.stat-number').text(stats.put);
        $('.stat-box').eq(4).find('.stat-number').text(stats.delete);
    }

    /**
     * Filter endpoints based on search and filters
     */
    function filterEndpoints() {
        const searchTerm = $('#search-endpoints').val().toLowerCase();
        const methodFilter = $('#filter-method').val();
        const namespaceFilter = $('#filter-namespace').val();

        let visibleCount = 0;

        $('.endpoint-row').each(function() {
            const $row = $(this);
            const $detailsRow = $row.next('.endpoint-details');

            const method = $row.data('method');
            const namespace = $row.data('namespace');
            const route = $row.find('.column-route code').text().toLowerCase();
            const description = $row.find('.column-description').text().toLowerCase();

            // Check if row matches filters
            let matches = true;

            // Search term
            if (searchTerm && !route.includes(searchTerm) && !description.includes(searchTerm)) {
                matches = false;
            }

            // Method filter
            if (methodFilter && method !== methodFilter) {
                matches = false;
            }

            // Namespace filter
            if (namespaceFilter && namespace !== namespaceFilter) {
                matches = false;
            }

            // Show/hide row
            if (matches) {
                $row.show();
                visibleCount++;
            } else {
                $row.hide();
                $detailsRow.hide();
            }
        });

        // Show message if no results
        if (visibleCount === 0) {
            if ($('.no-results-message').length === 0) {
                $('#endpoints-container table tbody').append(
                    '<tr class="no-results-message"><td colspan="5" style="text-align: center; padding: 20px;">No endpoints match your filters.</td></tr>'
                );
            }
        } else {
            $('.no-results-message').remove();
        }
    }

    /**
     * Toggle details view for an endpoint
     */
    function toggleDetails(index) {
        const $detailsRow = $('#details-' + index);
        const $button = $('.view-details[data-index="' + index + '"]');

        if ($detailsRow.is(':visible')) {
            $detailsRow.hide();
            $button.text('Details');
        } else {
            // Hide all other details
            $('.endpoint-details').hide();
            $('.view-details').text('Details');

            // Show this one
            $detailsRow.show();
            $button.text('Hide');
        }
    }

    /**
     * Export endpoints as JSON
     */
    function exportAsJSON() {
        const endpoints = gatherEndpointsForExport();

        const dataStr = JSON.stringify(endpoints, null, 2);
        const dataBlob = new Blob([dataStr], { type: 'application/json' });

        downloadFile(dataBlob, 'api-endpoints-' + getCurrentDate() + '.json');

        showNotice('Endpoints exported as JSON successfully!', 'success');
    }

    /**
     * Export endpoints as CSV
     */
    function exportAsCSV() {
        const endpoints = gatherEndpointsForExport();

        // CSV header
        let csv = 'Method,Route,Namespace,Description,Auth Required\n';

        // CSV rows
        endpoints.forEach(function(endpoint) {
            const row = [
                endpoint.method,
                endpoint.route,
                endpoint.namespace,
                escapeCsvValue(endpoint.description),
                endpoint.auth
            ];
            csv += row.join(',') + '\n';
        });

        const dataBlob = new Blob([csv], { type: 'text/csv' });
        downloadFile(dataBlob, 'api-endpoints-' + getCurrentDate() + '.csv');

        showNotice('Endpoints exported as CSV successfully!', 'success');
    }

    /**
     * Export endpoints as Markdown
     */
    function exportAsMarkdown() {
        const endpoints = gatherEndpointsForExport();

        let markdown = '# API Endpoints Reference\n\n';
        markdown += 'Generated on: ' + new Date().toLocaleString() + '\n\n';
        markdown += 'Total Endpoints: ' + endpoints.length + '\n\n';

        // Group by namespace
        const groupedEndpoints = {};
        endpoints.forEach(function(endpoint) {
            if (!groupedEndpoints[endpoint.namespace]) {
                groupedEndpoints[endpoint.namespace] = [];
            }
            groupedEndpoints[endpoint.namespace].push(endpoint);
        });

        // Generate markdown for each namespace
        Object.keys(groupedEndpoints).sort().forEach(function(namespace) {
            markdown += '## ' + namespace + '\n\n';

            groupedEndpoints[namespace].forEach(function(endpoint) {
                markdown += '### `' + endpoint.method + '` ' + endpoint.route + '\n\n';
                markdown += '**Description:** ' + endpoint.description + '\n\n';
                markdown += '**Authentication:** ' + endpoint.auth + '\n\n';

                // Add parameters if available
                const $detailsRow = $('.endpoint-row').filter(function() {
                    return $(this).find('.column-route code').text() === endpoint.route &&
                           $(this).data('method') === endpoint.method;
                }).next('.endpoint-details');

                const $paramsTable = $detailsRow.find('.params-table tbody tr');
                if ($paramsTable.length > 0) {
                    markdown += '**Parameters:**\n\n';
                    markdown += '| Parameter | Type | Required | Description |\n';
                    markdown += '|-----------|------|----------|-------------|\n';

                    $paramsTable.each(function() {
                        const $cells = $(this).find('td');
                        const param = $cells.eq(0).text().trim();
                        const type = $cells.eq(1).text().trim();
                        const required = $cells.eq(2).text().trim();
                        const desc = $cells.eq(3).text().trim();

                        markdown += '| ' + param + ' | ' + type + ' | ' + required + ' | ' + desc + ' |\n';
                    });

                    markdown += '\n';
                }

                markdown += '---\n\n';
            });
        });

        const dataBlob = new Blob([markdown], { type: 'text/markdown' });
        downloadFile(dataBlob, 'api-endpoints-' + getCurrentDate() + '.md');

        showNotice('Endpoints exported as Markdown successfully!', 'success');
    }

    /**
     * Gather visible endpoints for export
     */
    function gatherEndpointsForExport() {
        const endpoints = [];

        $('.endpoint-row:visible').each(function() {
            const $row = $(this);

            const endpoint = {
                method: $row.data('method'),
                namespace: $row.data('namespace'),
                route: $row.find('.column-route code').text(),
                description: $row.find('.column-description').text(),
                auth: $row.find('.column-auth span').text()
            };

            endpoints.push(endpoint);
        });

        return endpoints;
    }

    /**
     * Escape CSV value
     */
    function escapeCsvValue(value) {
        if (value.includes(',') || value.includes('"') || value.includes('\n')) {
            return '"' + value.replace(/"/g, '""') + '"';
        }
        return value;
    }

    /**
     * Download file
     */
    function downloadFile(blob, filename) {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    }

    /**
     * Get current date in YYYY-MM-DD format
     */
    function getCurrentDate() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    /**
     * Show notice message
     */
    function showNotice(message, type) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';

        const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');

        $('.hotsoup-api-endpoints-wrap h1').after($notice);

        // Auto-dismiss after 3 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

})(jQuery);
