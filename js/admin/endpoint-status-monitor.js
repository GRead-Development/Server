/**
 * Endpoint Status Monitor JavaScript
 * Handles testing and monitoring of REST API endpoints
 */

(function($) {
    'use strict';

    let endpoints = [];
    let testResults = [];
    let testingInProgress = false;
    let stopRequested = false;
    let currentTestIndex = 0;

    const EndpointMonitor = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#test-all-endpoints').on('click', () => this.startTesting());
            $('#test-failed-only').on('click', () => this.retestFailed());
            $('#stop-testing').on('click', () => this.stopTesting());
            $('#export-results').on('click', () => this.exportResults());

            $('#filter-success, #filter-error, #filter-auth, #filter-untested').on('change', () => this.applyFilters());

            $('.hs-modal-close').on('click', () => this.closeModal());
            $(window).on('click', (e) => {
                if ($(e.target).hasClass('hs-modal')) {
                    this.closeModal();
                }
            });

            $(document).on('click', '.view-details-btn', (e) => {
                const index = $(e.currentTarget).data('index');
                this.showDetails(index);
            });
        },

        startTesting: function() {
            if (testingInProgress) {
                alert('Testing is already in progress');
                return;
            }

            testingInProgress = true;
            stopRequested = false;
            currentTestIndex = 0;
            testResults = [];

            $('#test-all-endpoints').prop('disabled', true);
            $('#stop-testing').show();
            $('.hs-monitor-progress').show();
            $('.hs-monitor-summary').show();
            $('.hs-monitor-filters').show();

            this.loadEndpoints();
        },

        loadEndpoints: function() {
            $.ajax({
                url: hsEndpointMonitor.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'hs_get_all_endpoints',
                    nonce: hsEndpointMonitor.nonce
                },
                success: (response) => {
                    if (response.success) {
                        endpoints = response.data.endpoints;
                        testResults = endpoints.map(() => ({ status: 'untested' }));

                        $('.total-endpoints').text(endpoints.length);
                        $('#total-count').text(endpoints.length);

                        this.renderResultsTable();
                        this.updateSummary();
                        this.testNextEndpoint();
                    } else {
                        alert('Failed to load endpoints: ' + response.data.message);
                        this.resetUI();
                    }
                },
                error: (xhr) => {
                    alert('Error loading endpoints: ' + xhr.statusText);
                    this.resetUI();
                }
            });
        },

        testNextEndpoint: function() {
            if (stopRequested || currentTestIndex >= endpoints.length) {
                this.finishTesting();
                return;
            }

            const endpoint = endpoints[currentTestIndex];
            const index = currentTestIndex;

            this.testEndpoint(endpoint, index).then(() => {
                currentTestIndex++;
                this.updateProgress();
                this.updateResultRow(index);
                this.updateSummary();

                setTimeout(() => this.testNextEndpoint(), 100);
            });
        },

        testEndpoint: async function(endpoint, index) {
            const startTime = performance.now();

            let url = endpoint.full_url;
            let method = endpoint.method;
            let fetchOptions = {
                method: method,
                headers: {
                    'X-WP-Nonce': hsEndpointMonitor.restNonce,
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            };

            // Handle dynamic route parameters
            url = this.prepareTestUrl(endpoint);

            // For POST/PUT/PATCH, add minimal test data
            if (['POST', 'PUT', 'PATCH'].includes(method)) {
                fetchOptions.body = JSON.stringify({});
            }

            try {
                const response = await fetch(url, fetchOptions);
                const endTime = performance.now();
                const responseTime = Math.round(endTime - startTime);

                let responseData;
                const contentType = response.headers.get('content-type');

                if (contentType && contentType.includes('application/json')) {
                    responseData = await response.json();
                } else {
                    responseData = await response.text();
                }

                const result = {
                    status: this.determineStatus(response.status, endpoint),
                    httpStatus: response.status,
                    responseTime: responseTime,
                    response: responseData,
                    timestamp: new Date().toISOString(),
                    error: null
                };

                testResults[index] = result;
            } catch (error) {
                const endTime = performance.now();
                const responseTime = Math.round(endTime - startTime);

                testResults[index] = {
                    status: 'error',
                    httpStatus: 0,
                    responseTime: responseTime,
                    response: null,
                    timestamp: new Date().toISOString(),
                    error: error.message
                };
            }
        },

        prepareTestUrl: function(endpoint) {
            let url = endpoint.full_url;

            // Replace dynamic segments with test values
            url = url.replace(/\/\{id\}/g, '/1');
            url = url.replace(/\/\{user_id\}/g, '/1');
            url = url.replace(/\/\{book_id\}/g, '/1');
            url = url.replace(/\/\{isbn\}/g, '/9780000000000');
            url = url.replace(/\/\{gid\}/g, '/GR001');
            url = url.replace(/\/\{name\}/g, '/test');
            url = url.replace(/\/\([^)]+\)/g, '/1'); // Handle regex patterns

            return url;
        },

        determineStatus: function(httpStatus, endpoint) {
            // Success responses
            if (httpStatus >= 200 && httpStatus < 300) {
                return 'success';
            }

            // Auth required (expected for protected endpoints)
            if (httpStatus === 401 || httpStatus === 403) {
                if (endpoint.requires_auth) {
                    return 'auth_required';
                } else {
                    return 'error';
                }
            }

            // Client errors that might be expected
            if (httpStatus === 404) {
                // 404 might be expected for dynamic routes with test data
                if (endpoint.route.includes('{') || endpoint.route.includes('(')) {
                    return 'success'; // Consider it a success if endpoint exists but resource doesn't
                }
                return 'error';
            }

            if (httpStatus === 400 || httpStatus === 405) {
                // Bad request or method not allowed might be expected for endpoints requiring specific data
                return 'success';
            }

            // All other statuses are errors
            return 'error';
        },

        updateProgress: function() {
            const progress = Math.round((currentTestIndex / endpoints.length) * 100);
            $('.progress-bar').css('width', progress + '%');
            $('.current-progress').text(currentTestIndex);
        },

        updateSummary: function() {
            const summary = {
                success: 0,
                error: 0,
                auth_required: 0,
                untested: 0
            };

            testResults.forEach(result => {
                if (result.status in summary) {
                    summary[result.status]++;
                }
            });

            $('#success-count').text(summary.success);
            $('#error-count').text(summary.error);
            $('#auth-count').text(summary.auth_required);

            $('.filter-count-success').text(summary.success);
            $('.filter-count-error').text(summary.error);
            $('.filter-count-auth').text(summary.auth_required);
            $('.filter-count-untested').text(summary.untested);
        },

        renderResultsTable: function() {
            const tbody = $('#endpoint-results');
            tbody.empty();

            endpoints.forEach((endpoint, index) => {
                const row = this.createResultRow(endpoint, index);
                tbody.append(row);
            });
        },

        createResultRow: function(endpoint, index) {
            const result = testResults[index];
            const statusClass = result.status || 'untested';

            const row = $('<tr>')
                .addClass('endpoint-row')
                .addClass('status-' + statusClass)
                .attr('data-index', index)
                .attr('data-status', result.status || 'untested');

            const statusIcon = this.getStatusIcon(result.status);
            const statusText = this.getStatusText(result.status);

            row.append($('<td>').addClass('column-status').html(statusIcon + ' ' + statusText));
            row.append($('<td>').addClass('column-method').html(
                '<span class="method-badge method-' + endpoint.method.toLowerCase() + '">' +
                endpoint.method + '</span>'
            ));
            row.append($('<td>').addClass('column-endpoint').text(endpoint.route));

            const timeText = result.responseTime ? result.responseTime + 'ms' : '-';
            row.append($('<td>').addClass('column-response-time').text(timeText));

            const detailsBtn = $('<button>')
                .addClass('button button-small view-details-btn')
                .attr('data-index', index)
                .text('View Details');

            row.append($('<td>').addClass('column-details').append(detailsBtn));

            return row;
        },

        updateResultRow: function(index) {
            const row = $('.endpoint-row[data-index="' + index + '"]');
            const result = testResults[index];
            const endpoint = endpoints[index];

            const statusClass = result.status || 'untested';
            row.removeClass('status-success status-error status-auth_required status-untested')
               .addClass('status-' + statusClass)
               .attr('data-status', result.status || 'untested');

            const statusIcon = this.getStatusIcon(result.status);
            const statusText = this.getStatusText(result.status);

            row.find('.column-status').html(statusIcon + ' ' + statusText);

            const timeText = result.responseTime ? result.responseTime + 'ms' : '-';
            row.find('.column-response-time').text(timeText);
        },

        getStatusIcon: function(status) {
            const icons = {
                'success': '<span class="dashicons dashicons-yes-alt status-icon-success"></span>',
                'error': '<span class="dashicons dashicons-dismiss status-icon-error"></span>',
                'auth_required': '<span class="dashicons dashicons-lock status-icon-auth"></span>',
                'untested': '<span class="dashicons dashicons-minus status-icon-untested"></span>'
            };

            return icons[status] || icons.untested;
        },

        getStatusText: function(status) {
            const texts = {
                'success': 'OK',
                'error': 'Failed',
                'auth_required': 'Auth',
                'untested': 'Pending'
            };

            return texts[status] || 'Unknown';
        },

        showDetails: function(index) {
            const endpoint = endpoints[index];
            const result = testResults[index];

            let html = '<div class="endpoint-detail">';

            html += '<h3>Endpoint Information</h3>';
            html += '<table class="detail-table">';
            html += '<tr><th>Route:</th><td>' + this.escapeHtml(endpoint.route) + '</td></tr>';
            html += '<tr><th>Method:</th><td>' + endpoint.method + '</td></tr>';
            html += '<tr><th>Full URL:</th><td>' + this.escapeHtml(endpoint.full_url) + '</td></tr>';
            html += '<tr><th>Requires Auth:</th><td>' + (endpoint.requires_auth ? 'Yes' : 'No') + '</td></tr>';
            html += '</table>';

            if (result.status !== 'untested') {
                html += '<h3>Test Results</h3>';
                html += '<table class="detail-table">';
                html += '<tr><th>Status:</th><td>' + this.getStatusText(result.status) + '</td></tr>';
                html += '<tr><th>HTTP Status:</th><td>' + result.httpStatus + '</td></tr>';
                html += '<tr><th>Response Time:</th><td>' + result.responseTime + 'ms</td></tr>';
                html += '<tr><th>Timestamp:</th><td>' + result.timestamp + '</td></tr>';

                if (result.error) {
                    html += '<tr><th>Error:</th><td class="error-text">' + this.escapeHtml(result.error) + '</td></tr>';
                }

                html += '</table>';

                if (result.response) {
                    html += '<h3>Response Data</h3>';
                    html += '<pre class="response-data">' + this.escapeHtml(JSON.stringify(result.response, null, 2)) + '</pre>';
                }
            }

            html += '</div>';

            $('#endpoint-detail-content').html(html);
            $('#endpoint-detail-modal').show();
        },

        closeModal: function() {
            $('#endpoint-detail-modal').hide();
        },

        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        },

        applyFilters: function() {
            const showSuccess = $('#filter-success').is(':checked');
            const showError = $('#filter-error').is(':checked');
            const showAuth = $('#filter-auth').is(':checked');
            const showUntested = $('#filter-untested').is(':checked');

            $('.endpoint-row').each(function() {
                const status = $(this).attr('data-status');
                let show = false;

                if (status === 'success' && showSuccess) show = true;
                if (status === 'error' && showError) show = true;
                if (status === 'auth_required' && showAuth) show = true;
                if (status === 'untested' && showUntested) show = true;

                $(this).toggle(show);
            });
        },

        retestFailed: function() {
            if (testingInProgress) {
                alert('Testing is already in progress');
                return;
            }

            const failedIndices = [];
            testResults.forEach((result, index) => {
                if (result.status === 'error') {
                    failedIndices.push(index);
                }
            });

            if (failedIndices.length === 0) {
                alert('No failed endpoints to retest');
                return;
            }

            if (!confirm('Retest ' + failedIndices.length + ' failed endpoint(s)?')) {
                return;
            }

            testingInProgress = true;
            stopRequested = false;

            $('#test-failed-only').prop('disabled', true);
            $('#stop-testing').show();

            this.retestEndpoints(failedIndices, 0);
        },

        retestEndpoints: async function(indices, currentIndex) {
            if (stopRequested || currentIndex >= indices.length) {
                this.finishTesting();
                return;
            }

            const index = indices[currentIndex];
            const endpoint = endpoints[index];

            await this.testEndpoint(endpoint, index);
            this.updateResultRow(index);
            this.updateSummary();

            setTimeout(() => this.retestEndpoints(indices, currentIndex + 1), 100);
        },

        stopTesting: function() {
            stopRequested = true;
            this.finishTesting();
        },

        finishTesting: function() {
            testingInProgress = false;
            stopRequested = false;

            $('#test-all-endpoints').prop('disabled', false);
            $('#test-failed-only').prop('disabled', false).show();
            $('#stop-testing').hide();
            $('#export-results').show();
        },

        resetUI: function() {
            testingInProgress = false;
            $('#test-all-endpoints').prop('disabled', false);
            $('#stop-testing').hide();
            $('.hs-monitor-progress').hide();
        },

        exportResults: function() {
            const exportData = endpoints.map((endpoint, index) => {
                const result = testResults[index];
                return {
                    route: endpoint.route,
                    method: endpoint.method,
                    status: this.getStatusText(result.status),
                    httpStatus: result.httpStatus || 'N/A',
                    responseTime: result.responseTime ? result.responseTime + 'ms' : 'N/A',
                    requiresAuth: endpoint.requires_auth ? 'Yes' : 'No',
                    error: result.error || '',
                    timestamp: result.timestamp || ''
                };
            });

            const csv = this.convertToCSV(exportData);
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = 'endpoint-status-' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        },

        convertToCSV: function(data) {
            if (data.length === 0) return '';

            const headers = Object.keys(data[0]);
            const csvRows = [];

            csvRows.push(headers.join(','));

            for (const row of data) {
                const values = headers.map(header => {
                    const val = row[header];
                    return '"' + String(val).replace(/"/g, '""') + '"';
                });
                csvRows.push(values.join(','));
            }

            return csvRows.join('\n');
        }
    };

    $(document).ready(function() {
        EndpointMonitor.init();
    });

})(jQuery);
