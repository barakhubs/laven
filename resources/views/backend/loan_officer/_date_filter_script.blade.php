<script>
	(function($) {
		"use strict";

		if (!$.fn.daterangepicker || !document.getElementById('loan_officer_daterange')) {
			return;
		}

		var hasFilter = {{ ($date1 && $date2) ? 'true' : 'false' }};
		var start = hasFilter ? moment('{{ $date1 }}') : moment().startOf('month');
		var end   = hasFilter ? moment('{{ $date2 }}') : moment().endOf('month');

		$('#loan_officer_daterange').daterangepicker({
			startDate: start,
			endDate: end,
			autoUpdateInput: hasFilter,
			locale: { format: 'YYYY-MM-DD', cancelLabel: '{{ _lang("Clear") }}' },
			ranges: {
				'{{ _lang("Today") }}': [moment(), moment()],
				'{{ _lang("Yesterday") }}': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
				'{{ _lang("This Week") }}': [moment().startOf('week'), moment().endOf('week')],
				'{{ _lang("Last Week") }}': [moment().subtract(1, 'week').startOf('week'), moment().subtract(1, 'week').endOf('week')],
				'{{ _lang("This Month") }}': [moment().startOf('month'), moment().endOf('month')],
				'{{ _lang("Last Month") }}': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
				'{{ _lang("This Year") }}': [moment().startOf('year'), moment().endOf('year')],
				'{{ _lang("Last Year") }}': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
			}
		});

		$('#loan_officer_daterange').on('apply.daterangepicker', function(ev, picker) {
			$(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
			$('#date1').val(picker.startDate.format('YYYY-MM-DD'));
			$('#date2').val(picker.endDate.format('YYYY-MM-DD'));
		});

		$('#loan_officer_daterange').on('cancel.daterangepicker', function(ev, picker) {
			$(this).val('');
			$('#date1').val('');
			$('#date2').val('');
			$(this).closest('form').submit();
		});
	})(jQuery);
</script>
