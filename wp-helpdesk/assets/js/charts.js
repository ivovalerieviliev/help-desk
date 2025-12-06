/**
 * WP Help Desk Analytics Charts
 */
(function($) {
    'use strict';

    var WPHD_Charts = {
        
        init: function() {
            if (typeof Chart === 'undefined') {
                console.error('Chart.js is not loaded');
                return;
            }
            
            this.initStatusChart();
            this.initPriorityChart();
            this.initTrendsChart();
        },
        
        initStatusChart: function() {
            var ctx = document.getElementById('wphd-status-chart');
            if (!ctx || !wphdChartData.status) return;
            
            var data = wphdChartData.status;
            var labels = [];
            var values = [];
            var colors = [];
            
            for (var key in data) {
                labels.push(data[key].label);
                values.push(data[key].count);
                colors.push(data[key].color);
            }
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },
        
        initPriorityChart: function() {
            var ctx = document.getElementById('wphd-priority-chart');
            if (!ctx || !wphdChartData.priority) return;
            
            var data = wphdChartData.priority;
            var labels = [];
            var values = [];
            var colors = [];
            
            for (var key in data) {
                labels.push(data[key].label);
                values.push(data[key].count);
                colors.push(data[key].color);
            }
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Tickets by Priority',
                        data: values,
                        backgroundColor: colors,
                        borderRadius: 4
                    }]
                },
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
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        },
        
        initTrendsChart: function() {
            var ctx = document.getElementById('wphd-trends-chart');
            if (!ctx || !wphdChartData.trends) return;
            
            var data = wphdChartData.trends;
            var labels = [];
            var values = [];
            
            for (var i = 0; i < data.length; i++) {
                labels.push(data[i].date);
                values.push(data[i].count);
            }
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Tickets Created',
                        data: values,
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
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
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    }
                }
            });
        }
    };

    $(document).ready(function() {
        WPHD_Charts.init();
    });

})(jQuery);
