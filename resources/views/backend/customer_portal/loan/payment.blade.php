@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-6 offset-lg-3">
        <div class="card">
            <div class="card-header">
                <span class="header-title text-center">{{ _lang('Loan Repayment') }}</span>
            </div>
            <div class="card-body">
                <form method="post" class="validate" autocomplete="off" action="{{ route('loans.loan_payment', $loan->id) }}">
                    {{ csrf_field() }}
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Loan ID') }}</label>
                                <input type="text" class="form-control" name="loan_id" value="{{ $loan->loan_id }}" readonly="true" required>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="control-label">{{ _lang('Open Installments') }}</label>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>{{ _lang('Due Date') }}</th>
                                            <th class="text-right">{{ _lang('Penalty') }}</th>
                                            <th class="text-right">{{ _lang('Interest') }}</th>
                                            <th class="text-right">{{ _lang('Principal') }}</th>
                                            <th class="text-right">{{ _lang('Owed') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($arrears['installments'] as $installment)
                                        <tr class="{{ $installment['overdue'] ? 'text-danger' : '' }}">
                                            <td>{{ $installment['repayment']->repayment_date }}</td>
                                            <td class="text-right">{{ decimalPlace($installment['penalty'], currency($loan->currency->name)) }}</td>
                                            <td class="text-right">{{ decimalPlace($installment['interest'], currency($loan->currency->name)) }}</td>
                                            <td class="text-right">{{ decimalPlace($installment['principal'], currency($loan->currency->name)) }}</td>
                                            <td class="text-right">{{ decimalPlace($installment['total'], currency($loan->currency->name)) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        @if($arrears['overdue']['count'] > 0)
                                        <tr class="text-danger font-weight-bold">
                                            <td colspan="4">{{ _lang('Overdue now') }}</td>
                                            <td class="text-right">{{ decimalPlace($arrears['overdue']['total'], currency($loan->currency->name)) }}</td>
                                        </tr>
                                        @endif
                                        <tr class="font-weight-bold">
                                            <td colspan="4">{{ _lang('Whole loan') }}</td>
                                            <td class="text-right">{{ decimalPlace($arrears['owed']['total'], currency($loan->currency->name)) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Amount to Pay') }}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control float-field" name="total_amount" id="total_amount" value="{{ old('total_amount', $totalAmount) }}" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text currency">{{ $loan->currency->name }}</span>
                                    </div>
                                </div>
                                <small class="form-text text-muted">{{ _lang('You can pay any amount. It clears your oldest installment first (late penalty, then interest, then principal) and any balance goes to the next one.') }}</small>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Select Account') }}</label>
                                <select class="form-control auto-select" data-selected="{{ old('account_id') }}" name="account_id" required>
                                    <option value="">{{ _lang('Select One') }}</option>
                                    @foreach($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->account_number }} ({{ $account->savings_type->name }} - {{ $account->savings_type->currency->name }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Remarks') }}</label>
                                <textarea class="form-control" name="remarks">{{ old('remarks') }}</textarea>
                            </div>
                        </div>

                        <div class="col-md-12">
							<div class="form-group">
								<button type="submit" class="btn btn-primary  btn-block"><i class="ti-check-box"></i>&nbsp;{{ _lang('Make Payment') }}</button>
							</div>
						</div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
