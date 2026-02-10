@php
$deposit_requests = request_count('deposit_requests', true);
$withdraw_requests = request_count('withdraw_requests', true);
$member_requests = request_count('member_requests', true);
$pending_loans = request_count('pending_loans', true);
$upcomming_repayments = request_count('upcomming_repayments', true);
@endphp

<li>
	<a href="{{ route('dashboard.index') }}"><i class="fas fa-tachometer-alt"></i><span>{{ _lang('Dashboard') }}</span></a>
</li>

<li>
	<a href="{{ route('branches.index') }}"><i class="fas fa-building"></i><span>{{ _lang('Branches') }}</span></a>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-user-friends"></i><span>{{ _lang('Members') }} {!! xss_clean($member_requests) !!}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('members.index') }}">{{ _lang('View Members') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('members.create') }}">{{ _lang('Add Member') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('members.import') }}">{{ _lang('Bulk Import') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('custom_fields.index', ['members']) }}">{{ _lang('Custom Fields') }}</a></li>
		<li class="nav-item">
			<a class="nav-link" href="{{ route('members.pending_requests') }}">
			{{ _lang('Member Requests') }}
			{!! xss_clean($member_requests) !!}
			</a>
		</li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-hand-holding-usd"></i><span>{{ _lang('Loans') }} {!! xss_clean($pending_loans) !!}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('loans.index') }}">{{ _lang('All Loans') }}</a></li>
		<li class="nav-item">
			<a class="nav-link" href="{{ route('loans.filter', 'pending') }}">
				{{ _lang('Pending Loans') }}
				{!! xss_clean($pending_loans) !!}
			</a>
		</li>
		<li class="nav-item"><a class="nav-link" href="{{ route('loans.filter', 'active') }}">{{ _lang('Active Loans') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('loans.admin_calculator') }}">{{ _lang('Loan Calculator') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('loan_products.index') }}">{{ _lang('Loan Products') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('custom_fields.index', ['loans']) }}">{{ _lang('Custom Fields') }}</a></li>
	</ul>
</li>

<li><a href="{{ route('loans.upcoming_loan_repayments') }}"><i class="fas fa-calendar-alt"></i><span>{{ _lang('Upcomming Payments') }} {!! xss_clean($upcomming_repayments) !!}</span></a></li>
<li><a href="{{ route('loan_payments.index') }}"><i class="fas fa-receipt"></i><span>{{ _lang('Loan Repayments') }}</span></a></li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-landmark"></i><span>{{ _lang('Accounts') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('savings_accounts.index') }}">{{ _lang('Member Accounts') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('interest_calculation.calculator') }}">{{ _lang('Interest Calculation') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('savings_products.index') }}">{{ _lang('Account Types') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-coins"></i><span>{{ _lang('Deposit') }} {!! xss_clean($deposit_requests) !!}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('transactions.create') }}?type=deposit">{{ _lang('Deposit Money') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('deposit_requests.index') }}">
				{{ _lang('Deposit Requests') }}
				{!! xss_clean($deposit_requests) !!}
			</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-money-check"></i><span>{{ _lang('Withdraw') }} {!! xss_clean($withdraw_requests) !!}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('transactions.create') }}?type=withdraw">{{ _lang('Withdraw Money') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('withdraw_requests.index') }}">
				{{ _lang('Withdraw Requests') }}
				{!! xss_clean($withdraw_requests) !!}
			</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-wallet"></i><span>{{ _lang('Transactions') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('transactions.create') }}">{{ _lang('New Transaction') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('transactions.index') }}">{{ _lang('Transaction History') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('transaction_categories.index') }}">{{ _lang('Transaction Categories') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-money-bill-wave"></i><span>{{ _lang('Expense') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('expenses.index') }}">{{ _lang('All Expense') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('expense_categories.index') }}">{{ _lang('Expense Categories') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-list-ul"></i><span>{{ _lang('Deposit Methods') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('payment_gateways.index') }}">{{ _lang('Automatic Gateways') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('deposit_methods.index') }}">{{ _lang('Manual Gateways') }}</a></li>
	</ul>
</li>

<li>
	<a href="{{ route('withdraw_methods.index') }}"><i class="fas fa-clipboard-list"></i><span>{{ _lang('Withdraw Methods') }}</span></a>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-landmark"></i><span>{{ _lang('Bank Accounts') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('bank_accounts.index') }}">{{ _lang('Bank Accounts') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('bank_transactions.index') }}">{{ _lang('Bank Transactions') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="ti-user"></i><span>{{ _lang('User Management') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('users.index') }}">{{ _lang('All Users') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('roles.index') }}">{{ _lang('User Roles') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('permission.index') }}">{{ _lang('Access Control') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="ti-world"></i><span>{{ _lang('Languages') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('languages.index') }}">{{ _lang('All Language') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('languages.create') }}">{{ _lang('Add New') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="ti-bar-chart"></i><span>{{ _lang('Reports') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.account_statement') }}">{{ _lang('Account Statement') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.account_balances') }}">{{ _lang('Account Balance') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.loan_report') }}">{{ _lang('Loan Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.loan_due_report') }}">{{ _lang('Loan Due Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.loan_repayment_report') }}">{{ _lang('Loan Repayment Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.transactions_report') }}">{{ _lang('Transaction Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.expense_report') }}">{{ _lang('Expense Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.cash_in_hand') }}">{{ _lang('Cash In Hand') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.bank_transactions') }}">{{ _lang('Bank Transactions') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.bank_balances') }}">{{ _lang('Bank Account Balance') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.revenue_report') }}">{{ _lang('Revenue Report') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="ti-settings"></i><span>{{ _lang('System Settings') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('settings.update_settings') }}">{{ _lang('General Settings') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('currency.index') }}">{{ _lang('Supported Currency') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('notification_templates.index') }}">{{ _lang('Notification Templates') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('database_backups.list') }}">{{ _lang('Database Backup') }}</a></li>
	</ul>
</li>