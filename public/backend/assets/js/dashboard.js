(function($) {
    "use strict";

    // Loan Recovery Pattern Chart
    if (document.getElementById('recoveryPattern')) {
        var chartCurrency = _currency;
        const ctx = document.getElementById('recoveryPattern').getContext('2d');
        const recoveryChart = new Chart(ctx, {
            data: {
                labels: [],
                datasets: [
                    { type: 'bar', label: $lang_recovered, data: [], backgroundColor: 'rgba(46, 204, 113, 0.85)', borderRadius: 6, stack: 'recovery', yAxisID: 'y' },
                    { type: 'bar', label: $lang_not_recovered, data: [], backgroundColor: 'rgba(231, 76, 60, 0.85)', borderRadius: 6, stack: 'recovery', yAxisID: 'y' },
                    { type: 'bar', label: $lang_pending, data: [], backgroundColor: 'rgba(241, 196, 15, 0.85)', borderRadius: 6, stack: 'recovery', yAxisID: 'y' },
                    { type: 'line', label: $lang_released, data: [], borderColor: 'rgba(52, 152, 219, 1)', backgroundColor: 'rgba(52, 152, 219, 1)', borderWidth: 2, tension: 0.3, yAxisID: 'y1' }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: {
                        stacked: true,
                        position: 'left',
                        title: { display: true, text: $lang_recovered + ' / ' + $lang_not_recovered },
                        ticks: { callback: function(value) { return chartCurrency + ' ' + value; } }
                    },
                    y1: {
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: $lang_released },
                        ticks: { callback: function(value) { return chartCurrency + ' ' + value; } }
                    }
                },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.dataset.label + ': ' + chartCurrency + ' ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        function loadChart(currency_id) {
            var link = _url + "/dashboard/json_recovery_pattern/" + currency_id;
            $.ajax({
                url: link,
                success: function (data) {
                    var json = JSON.parse(data);

                    recoveryChart.data.labels = json.labels;
                    recoveryChart.data.datasets[0].data = json.recovered;
                    recoveryChart.data.datasets[1].data = json.missed;
                    recoveryChart.data.datasets[2].data = json.pending;
                    recoveryChart.data.datasets[3].data = json.released;
                    recoveryChart.update();

                    var totalRecovered = json.recovered.reduce((a, b) => a + b, 0);
                    var totalMissed    = json.missed.reduce((a, b) => a + b, 0);
                    var totalReleased  = json.released.reduce((a, b) => a + b, 0);
                    var dueSoFar       = totalRecovered + totalMissed;
                    var rate = dueSoFar > 0 ? ((totalRecovered / dueSoFar) * 100).toFixed(1) : 0;

                    $('#recoverySummary').html(
                        '<div class="px-3"><h5 class="mb-0 text-primary"><b>' + chartCurrency + ' ' + totalReleased.toLocaleString() + '</b></h5><small>' + $lang_released + '</small></div>' +
                        '<div class="px-3"><h5 class="mb-0 text-success"><b>' + chartCurrency + ' ' + totalRecovered.toLocaleString() + '</b></h5><small>' + $lang_recovered + '</small></div>' +
                        '<div class="px-3"><h5 class="mb-0 text-danger"><b>' + chartCurrency + ' ' + totalMissed.toLocaleString() + '</b></h5><small>' + $lang_not_recovered + '</small></div>' +
                        '<div class="px-3"><h5 class="mb-0"><b>' + rate + '%</b></h5><small>' + $lang_recovery_rate + '</small></div>'
                    );
                }
            });
        }

        loadChart(_base_currency_id);
        $(document).on('change', '.filter-select', function(){
            var currency_id = $(this).val();
            chartCurrency = $(this).find(':selected').data('symbol');
            loadChart(currency_id);
        });
    }

    $(document).on('change', '#branch-switch', function(){});

})(jQuery);