<table class="table table-bordered">
	<tr><td>{{ _lang('Expense Date') }}</td><td>{{ $expense->expense_date }}</td></tr>
	<tr><td>{{ _lang('Expense Category') }}</td><td>{{ $expense->expense_category->name }}</td></tr>
	<tr><td>{{ _lang('Amount') }}</td><td>{{ decimalPlace($expense->amount, currency()) }}</td></tr>
	<tr><td>{{ _lang('Reference') }}</td><td>{{ $expense->reference }}</td></tr>
	<tr><td>{{ _lang('Branch') }}</td><td>{{ $expense->branch->name }}</td></tr>
	<tr><td>{{ _lang('Note') }}</td><td>{{ $expense->note }}</td></tr>
	<tr>
		<td>{{ _lang('Attachment') }}</td>
		<td>
		@if($expense->attachment != '')
		 	<a href="{{ asset('public/uploads/media/'.$expense->attachment) }}" target="_blank">{{ $expense->attachment }}</a>
		@endif
		</td>
	</tr>
	<tr><td>{{ _lang('Created By') }}</td><td>{{ $expense->created_by->name }} ({{ $expense->created_at }})</td></tr>
	<tr><td>{{ _lang('Updated By') }}</td><td>{{ $expense->updated_by->name }} ({{ $expense->updated_at }})</td></tr>
</table>

