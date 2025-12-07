/**
 * WP Help Desk Reports JavaScript
 */

(function ($) {
    'use strict';

    const WPHDReports = {
        charts: {},
        currentFilters: {},

        init: function () {
            this.bindEvents();
            this.loadReportData();
        },

        bindEvents: function () {
            $('#wphd-generate-report').on('click', (e) => {
                e.preventDefault();
                this.loadReportData();
            });

            $('#wphd-export-csv').on('click', (e) => {
                e.preventDefault();
                this.exportCSV();
            });

            $('#wphd-print-report').on('click', (e) => {
                e.preventDefault();
                window.print();
            });

            $('#wphd-date-range').on('change', (e) => {
                this.handleDateRangeChange(e.target.value);
            });

            $('#wphd-view-user').on('change', (e) => {
                this.loadReportData();
            });
        },

        handleDateRangeChange: function (value) {
            const today = new Date();
            let startDate, endDate = this.formatDate(today);

            switch (value) {
                case 'today':
                    startDate = endDate;
                    break;
                case 'week':
                    startDate = this.formatDate(new Date(today.setDate(today.getDate() - 7)));
                    break;
                case 'month':
                    startDate = this.formatDate(new Date(today.setMonth(today.getMonth() - 1)));
                    break;
                case 'quarter':
                    startDate = this.formatDate(new Date(today.setMonth(today.getMonth() - 3)));
                    break;
                case 'year':
                    startDate = this.formatDate(new Date(today.setFullYear(today.getFullYear() - 1)));
                    break;
                case 'custom':
                    $('#wphd-custom-dates').show();
                    return;
                default:
                    startDate = this.formatDate(new Date(today.setDate(today.getDate() - 30)));
            }

            $('#wphd-custom-dates').hide();
            $('#wphd-date-start').val(startDate);
            $('#wphd-date-end').val(endDate);
        },

        formatDate: function (date) {
            return date.toISOString().split('T')[0];
        },

        getFilters: function () {
            return {
                date_start: $('#wphd-date-start').val() || this.formatDate(new Date(new Date().setDate(new Date().getDate() - 30))),
                date_end: $('#wphd-date-end').val() || this.formatDate(new Date()),
                status: $('#wphd-filter-status').val() || '',
                priority: $('#wphd-filter-priority').val() || '',
                category: $('#wphd-filter-category').val() || '',
                assignee: $('#wphd-filter-assignee').val() || '',
                user_id: $('#wphd-view-user').val() || 0
            };
        },

        loadReportData: function () {
            const filters = this.getFilters();
            this.currentFilters = filters;

            $('#wphd-reports-loading').show();
            $('#wphd-reports-content').css('opacity', '0.5');

            $.ajax({
                url: wpHelpDesk.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wphd_get_report_data',
                    nonce: wpHelpDesk.nonce,
                    ...filters
                },
                success: (response) => {
                    if (response.success) {
                        this.renderReports(response.data);
                    } else {
                        this.showError(response.data?.message || wpHelpDesk.i18n.error);
                    }
                },
                error: () => {
                    this.showError(wpHelpDesk.i18n.error);
                },
                complete: () => {
                    $('#wphd-reports-loading').hide();
                    $('#wphd-reports-content').css('opacity', '1');
                }
            });
        },

        renderReports: function (data) {
            // Update page title if user-specific
            if (this.currentFilters.user_id > 0) {
                const userName = $('#wphd-view-user option:selected').text();
                $('#wphd-report-title').text(wpHelpDesk.i18n.report_for + ' ' + userName);
            } else {
                $('#wphd-report-title').text(wpHelpDesk.i18n.reports);
            }

            // Render summary cards
            this.renderSummaryCards(data.summary);

            // Render charts
            this.renderTicketsOverTime(data.tickets_over_time);
            this.renderTicketsByStatus(data.tickets_by_status);
            this.renderTicketsByPriority(data.tickets_by_priority);
            this.renderTicketsByCategory(data.tickets_by_category);
            
            // Only show agent performance if not viewing specific user
            if (this.currentFilters.user_id === 0 || this.currentFilters.user_id === '0') {
                $('#wphd-agent-performance-chart').parent().show();
                this.renderAgentPerformance(data.agent_performance);
            } else {
                $('#wphd-agent-performance-chart').parent().hide();
            }

            this.renderResolutionTimeTrend(data.resolution_time_trend);

            // Render data table
            this.renderTicketTable(data.ticket_details);

            // Show user comparison if available
            if (data.user_comparison) {
                this.renderUserComparison(data.user_comparison);
            } else {
                $('#wphd-user-comparison').hide();
            }
        },

        renderSummaryCards: function (summary) {
            $('#wphd-stat-total').text(summary.total_tickets);
            $('#wphd-stat-open').text(summary.open_tickets);
            $('#wphd-stat-closed').text(summary.closed_tickets);
            $('#wphd-stat-avg-resolution').text(summary.avg_resolution_time + 'h');
            $('#wphd-stat-sla-compliance').text(summary.sla_compliance_rate + '%');
            $('#wphd-stat-avg-response').text(summary.avg_first_response_time + 'h');
        },

        renderTicketsOverTime: function (data) {
            const ctx = document.getElementById('wphd-tickets-over-time-chart');
            if (!ctx) return;

            if (this.charts.ticketsOverTime) {
                this.charts.ticketsOverTime.destroy();
            }

            this.charts.ticketsOverTime = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: data.datasets.map(ds => ({
                        ...ds,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        tension: 0.4
                    }))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        },

        renderTicketsByStatus: function (data) {
            const ctx = document.getElementById('wphd-tickets-by-status-chart');
            if (!ctx) return;

            if (this.charts.ticketsByStatus) {
                this.charts.ticketsByStatus.destroy();
            }

            this.charts.ticketsByStatus = new Chart(ctx, {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        },

        renderTicketsByPriority: function (data) {
            const ctx = document.getElementById('wphd-tickets-by-priority-chart');
            if (!ctx) return;

            if (this.charts.ticketsByPriority) {
                this.charts.ticketsByPriority.destroy();
            }

            this.charts.ticketsByPriority = new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        renderTicketsByCategory: function (data) {
            const ctx = document.getElementById('wphd-tickets-by-category-chart');
            if (!ctx) return;

            if (this.charts.ticketsByCategory) {
                this.charts.ticketsByCategory.destroy();
            }

            this.charts.ticketsByCategory = new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        renderAgentPerformance: function (data) {
            const ctx = document.getElementById('wphd-agent-performance-chart');
            if (!ctx) return;

            if (this.charts.agentPerformance) {
                this.charts.agentPerformance.destroy();
            }

            this.charts.agentPerformance = new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        renderResolutionTimeTrend: function (data) {
            const ctx = document.getElementById('wphd-resolution-time-trend-chart');
            if (!ctx) return;

            if (this.charts.resolutionTimeTrend) {
                this.charts.resolutionTimeTrend.destroy();
            }

            this.charts.resolutionTimeTrend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: data.datasets.map(ds => ({
                        ...ds,
                        fill: false,
                        tension: 0.4
                    }))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        renderTicketTable: function (tickets) {
            const tbody = $('#wphd-tickets-table tbody');
            tbody.empty();

            if (tickets.length === 0) {
                tbody.append('<tr><td colspan="9">' + wpHelpDesk.i18n.no_tickets + '</td></tr>');
                return;
            }

            tickets.forEach(ticket => {
                const row = $('<tr>');
                row.append(`<td><strong>#${ticket.id}</strong></td>`);
                row.append(`<td>${this.escapeHtml(ticket.subject)}</td>`);
                row.append(`<td>${this.escapeHtml(ticket.status)}</td>`);
                row.append(`<td>${this.escapeHtml(ticket.priority)}</td>`);
                row.append(`<td>${this.escapeHtml(ticket.category)}</td>`);
                row.append(`<td>${this.escapeHtml(ticket.assignee)}</td>`);
                row.append(`<td>${ticket.created_date}</td>`);
                row.append(`<td>${ticket.resolved_date || '-'}</td>`);
                row.append(`<td>${ticket.resolution_time ? ticket.resolution_time + 'h' : '-'}</td>`);
                tbody.append(row);
            });
        },

        renderUserComparison: function (comparison) {
            $('#wphd-user-comparison').show();
            
            const user = comparison.user_stats;
            const team = comparison.team_stats;
            const diff = comparison.comparison;

            $('#wphd-user-tickets').text(user.total_tickets);
            $('#wphd-user-resolution').text(user.avg_resolution_time + 'h');
            $('#wphd-user-sla').text(user.sla_compliance_rate + '%');

            // Show comparison indicators
            this.updateComparisonIndicator('#wphd-user-tickets-diff', diff.tickets_diff);
            this.updateComparisonIndicator('#wphd-user-resolution-diff', diff.resolution_diff, true); // inverse - lower is better
            this.updateComparisonIndicator('#wphd-user-sla-diff', diff.sla_diff);
        },

        updateComparisonIndicator: function (selector, value, inverse = false) {
            const element = $(selector);
            const absValue = Math.abs(value).toFixed(1);
            
            if (value === 0) {
                element.text('→ ' + wpHelpDesk.i18n.team_avg);
                element.removeClass('positive negative');
                return;
            }

            const isPositive = inverse ? value < 0 : value > 0;
            const sign = value > 0 ? '+' : '';
            
            // For percentage differences, always show % symbol
            element.text(sign + absValue + '%');
            element.removeClass('positive negative');
            element.addClass(isPositive ? 'positive' : 'negative');
        },

        exportCSV: function () {
            const filters = this.currentFilters;

            $('#wphd-export-csv').prop('disabled', true).text(wpHelpDesk.i18n.exporting);

            $.ajax({
                url: wpHelpDesk.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wphd_export_report_csv',
                    nonce: wpHelpDesk.nonce,
                    ...filters
                },
                success: (response) => {
                    if (response.success && response.data.csv) {
                        this.downloadCSV(response.data.csv);
                    } else {
                        alert(response.data?.message || wpHelpDesk.i18n.error);
                    }
                },
                error: () => {
                    alert(wpHelpDesk.i18n.error);
                },
                complete: () => {
                    $('#wphd-export-csv').prop('disabled', false).text(wpHelpDesk.i18n.export_csv);
                }
            });
        },

        downloadCSV: function (csv) {
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'helpdesk-report-' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        },

        escapeHtml: function (text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text || '').replace(/[&<>"']/g, m => map[m]);
        },

        showError: function (message) {
            alert(message);
        }
    };

    $(document).ready(function () {
        if ($('#wphd-reports-page').length) {
            WPHDReports.init();
        }
    });

})(jQuery);
