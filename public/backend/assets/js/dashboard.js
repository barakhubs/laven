(function($) {
    "use strict";

    // Loan Recovery Pattern Chart
    if (document.getElementById('recoveryPattern')) {
        var link = _url + "/dashboard/json_recovery_pattern";
        $.ajax({
            url: link,
            success: function (data) {
                var json = JSON.parse(data);

                // Summary strip (totals across the 6 months)
                var totalExpected  = json.expected.reduce((a, b) => a + b, 0);
                var totalRecovered = json.recovered.reduce((a, b) => a + b, 0);
                var totalMissed    = json.missed.reduce((a, b) => a + b, 0);
                var rate = totalExpected > 0 ? ((totalRecovered / totalExpected) * 100).toFixed(1) : 0;

                $('#recoverySummary').html(
                    '<div class="px-3"><h5 class="mb-0 text-success"><b>' + _currency + ' ' + totalRecovered.toLocaleString() + '</b></h5><small>' + $lang_recovered + '</small></div>' +
                    '<div class="px-3"><h5 class="mb-0 text-danger"><b>' + _currency + ' ' + totalMissed.toLocaleString() + '</b></h5><small>' + $lang_not_recovered + '</small></div>' +
                    '<div class="px-3"><h5 class="mb-0 text-primary"><b>' + rate + '%</b></h5><small>' + $lang_recovery_rate + '</small></div>'
                );

                const ctx = document.getElementById('recoveryPattern').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: json.labels,
                        datasets: [
                            {
                                label: $lang_recovered,
                                data: json.recovered,
                                backgroundColor: 'rgba(46, 204, 113, 0.85)',
                                borderRadius: 6,
                                stack: 'recovery'
                            },
                            {
                                label: $lang_not_recovered,
                                data: json.missed,
                                backgroundColor: 'rgba(231, 76, 60, 0.85)',
                                borderRadius: 6,
                                stack: 'recovery'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            x: { stacked: true, grid: { display: false } },
                            y: {
                                stacked: true,
                                ticks: {
                                    callback: function(value) { return _currency + ' ' + value; }
                                }
                            }
                        },
                        plugins: {
                            legend: { position: 'top' },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return ' ' + context.dataset.label + ': ' + _currency + ' ' + context.parsed.y.toLocaleString();
                                    },
                                    footer: function(items) {
                                        var idx = items[0].dataIndex;
                                        return $lang_expected + ': ' + _currency + ' ' + json.expected[idx].toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
    }

    $(document).on('change', '#branch-switch', function(){});

})(jQuery);