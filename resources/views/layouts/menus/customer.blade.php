<li>
	<a href="{{ route('dashboard.index') }}"><i class="fas fa-tachometer-alt"></i><span>{{ _lang('Dashboard') }}</span></a>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-hand-holding-usd"></i><span>{{ _lang('My Loans') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
        <li class="nav-item"><a class="nav-link" href="{{ route('loans.my_loans') }}">{{ _lang('My Loans') }}</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('loans.loan_products') }}">{{ _lang('Apply New Loan') }}</a></li>
	</ul>
</li>

<li>
	<a href="{{ route('loans.calculator') }}"><i class="fas fa-calculator"></i><span>{{ _lang('Loan Calculator') }}</span></a>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-exchange-alt"></i><span>{{ _lang('Transfer Money') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
        <li class="nav-item"><a class="nav-link" href="{{ route('transfer.own_account_transfer') }}">{{ _lang('Own Account Transfer') }}</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('transfer.other_account_transfer') }}">{{ _lang('Others Account Transfer') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-coins"></i><span>{{ _lang('Deposit Money') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
        <li class="nav-item"><a class="nav-link" href="{{ route('deposit.automatic_methods') }}">{{ _lang('Automatic Deposit') }}</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('deposit.manual_methods') }}">{{ _lang('Manual Deposit') }}</a></li>
	</ul>
</li>

<li>
	<a href="{{ route('withdraw.manual_methods') }}"><i class="fas fa-money-check"></i><span>{{ _lang('Withdraw Money') }}</span></a>
</li>

<li>
	<a href="{{ route('trasnactions.transaction_requests') }}?type=deposit_requests"><i class="fas fa-life-ring"></i><span>{{ _lang('Pending Requests') }}</span></a>
</li>

<li>
	<a href="javascript: void(0);"><i class="ti-bar-chart"></i><span>{{ _lang('Reports') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('customer_reports.account_statement') }}">{{ _lang('Account Statement') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('customer_reports.transactions_report') }}">{{ _lang('Transaction Report') }}</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('customer_reports.account_balances') }}">{{ _lang('Account Balance') }}</a></li>
    </ul>
</li>