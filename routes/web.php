<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Select2Controller;
use App\Http\Controllers\UtilityController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\InterestController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuarantorController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\CustomFieldController;
use App\Http\Controllers\LoanPaymentController;
use App\Http\Controllers\LoanProductController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DepositMethodController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\DepositRequestController;
use App\Http\Controllers\LoanCollateralController;
use App\Http\Controllers\MemberDocumentController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\SavingsAccountController;
use App\Http\Controllers\SavingsProductController;
use App\Http\Controllers\WithdrawMethodController;
use App\Http\Controllers\BankTransactionController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\WithdrawRequestController;
use App\Http\Controllers\TransactionCategoryController;
use App\Http\Controllers\NotificationTemplateController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
 */

Route::group(['middleware' => ['install']], function () {

    Route::get('/', function () {
        return redirect('login');
    });

    Auth::routes();
    Route::get('/logout', [LoginController::class, 'logout']);

    Route::get('verify_2fa/resend', [TwoFactorController::class, 'resend'])->name('verify_2fa.resend');
    Route::get('verify_2fa', [TwoFactorController::class, 'index'])->name('verify_2fa.index');
    Route::post('verify_2fa/verify', [TwoFactorController::class, 'verify'])->name('verify_2fa.verify');

    Route::group(['middleware' => ['auth', 'verified']], function () {

        Route::group(['middleware' => ['Email2FA']], function () {

            Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

            //Profile Controller
            Route::get('profile', [ProfileController::class, 'index'])->name('profile.index');
            Route::get('profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::post('profile/update', [ProfileController::class, 'update'])->name('profile.update')->middleware('demo');
            Route::get('profile/change_password', [ProfileController::class, 'change_password'])->name('profile.change_password');
            Route::post('profile/update_password', [ProfileController::class, 'update_password'])->name('profile.update_password')->middleware('demo');
            Route::get('profile/notification_mark_as_read/{id}', [ProfileController::class, 'notification_mark_as_read'])->name('profile.notification_mark_as_read');
            Route::get('profile/show_notification/{id}', [ProfileController::class, 'show_notification'])->name('profile.show_notification');

            /** Admin Only Route **/
            Route::group(['middleware' => ['admin', 'demo'], 'prefix' => 'admin'], function () {

                //User Management
                Route::resource('users', UserController::class);

                //User Roles
                Route::resource('roles', RoleController::class);

                //Payment Gateways
                Route::resource('payment_gateways', PaymentGatewayController::class)->except([
                    'create', 'store', 'show', 'destroy',
                ]);

                //Branch Controller
                Route::resource('branches', BranchController::class);

                //Savings Products
                Route::resource('savings_products', SavingsProductController::class);

                //Transaction Category
                Route::resource('transaction_categories', TransactionCategoryController::class);

                //Loan Products
                Route::resource('loan_products', LoanProductController::class)->except('show');

                //Expense Categories
                Route::resource('expense_categories', ExpenseCategoryController::class)->except('show');

                //Currency List
                Route::resource('currency', CurrencyController::class);

                //Deposit Methods
                Route::resource('deposit_methods', DepositMethodController::class)->except([
                    'show',
                ]);

                //Withdraw Methods
                Route::resource('withdraw_methods', WithdrawMethodController::class)->except([
                    'show',
                ]);

                //Permission Controller
                Route::get('permission/access_control', [PermissionController::class, 'index'])->name('permission.index');
                Route::get('permission/access_control/{user_id?}', [PermissionController::class, 'show'])->name('permission.show');
                Route::post('permission/store', [PermissionController::class, 'store'])->name('permission.store');

                //Language Controller
                Route::resource('languages', LanguageController::class);

                //Utility Controller
                Route::match(['get', 'post'], 'administration/general_settings/{store?}', [UtilityController::class, 'settings'])->name('settings.update_settings');
                Route::post('administration/upload_logo', [UtilityController::class, 'upload_logo'])->name('settings.uplaod_logo');
                Route::get('administration/database_backup_list', [UtilityController::class, 'database_backup_list'])->name('database_backups.list');
                Route::get('administration/create_database_backup', [UtilityController::class, 'create_database_backup'])->name('database_backups.create');
                Route::delete('administration/destroy_database_backup/{id}', [UtilityController::class, 'destroy_database_backup'])->name('database_backups.destroy_database_backup');
                Route::get('administration/download_database_backup/{id}', [UtilityController::class, 'download_database_backup'])->name('database_backups.download');
                Route::post('administration/remove_cache', [UtilityController::class, 'remove_cache'])->name('settings.remove_cache');
                Route::post('administration/send_test_email', [UtilityController::class, 'send_test_email'])->name('settings.send_test_email');

                //Notification Template
                Route::resource('notification_templates', NotificationTemplateController::class)->only([
                    'index', 'edit', 'update',
                ]);

            });

            /** Dynamic Permission **/
            Route::group(['middleware' => ['permission'], 'prefix' => 'admin'], function () {

                //Dashboard Widget
                Route::get('dashboard/total_customer_widget', [DashboardController::class, 'total_customer_widget'])->name('dashboard.total_customer_widget');
                Route::get('dashboard/deposit_requests_widget', [DashboardController::class, 'deposit_requests_widget'])->name('dashboard.deposit_requests_widget');
                Route::get('dashboard/withdraw_requests_widget', [DashboardController::class, 'withdraw_requests_widget'])->name('dashboard.withdraw_requests_widget');
                Route::get('dashboard/loan_requests_widget', [DashboardController::class, 'loan_requests_widget'])->name('dashboard.loan_requests_widget');
                Route::get('dashboard/expense_overview_widget', [DashboardController::class, 'expense_overview_widget'])->name('dashboard.expense_overview_widget');
                Route::get('dashboard/deposit_withdraw_analytics', [DashboardController::class, 'deposit_withdraw_analytics'])->name('dashboard.deposit_withdraw_analytics');
                Route::get('dashboard/recent_transaction_widget', [DashboardController::class, 'recent_transaction_widget'])->name('dashboard.recent_transaction_widget');
                Route::get('dashboard/due_loan_list', [DashboardController::class, 'due_loan_list'])->name('dashboard.due_loan_list');
                Route::get('dashboard/active_loan_balances',  [DashboardController::class, 'active_loan_balances'])->name('dashboard.active_loan_balances');

                //Member Controller
                Route::match(['get', 'post'], 'members/import', [MemberController::class, 'import'])->name('members.import');
                Route::match(['get', 'post'], 'members/accept_request/{id}', [MemberController::class, 'accept_request'])->name('members.accept_request');
                Route::get('members/reject_request/{id}', [MemberController::class, 'reject_request'])->name('members.reject_request');
                Route::get('members/pending_requests', [MemberController::class, 'pending_requests'])->name('members.pending_requests');
                Route::get('members/get_member_transaction_data/{member_id}', [MemberController::class, 'get_member_transaction_data']);
                Route::get('members/get_table_data', [MemberController::class, 'get_table_data']);
                Route::post('members/send_email', [MemberController::class, 'send_email'])->name('members.send_email');
                Route::post('members/send_sms', [MemberController::class, 'send_sms'])->name('members.send_sms');
                Route::resource('members', MemberController::class)->middleware("demo:PUT|PATCH|DELETE");

                //Custom Field Controller
                Route::resource('custom_fields', CustomFieldController::class)->except(['index', 'show'])->middleware("demo");
                Route::get('custom_fields/{table}', [CustomFieldController::class, 'index'])->name('custom_fields.index');

                //Members Documents
                Route::get('member_documents/{member_id}', [MemberDocumentController::class, 'index'])->name('member_documents.index');
                Route::get('member_documents/create/{member_id}', [MemberDocumentController::class, 'create'])->name('member_documents.create');
                Route::resource('member_documents', MemberDocumentController::class)->except(['index', 'create', 'show']);

                //Savings Accounts
                Route::get('savings_accounts/get_account_by_member_id/{member_id}', [SavingsAccountController::class, 'get_account_by_member_id']);
                Route::get('savings_accounts/get_table_data', [SavingsAccountController::class, 'get_table_data']);
                Route::resource('savings_accounts', SavingsAccountController::class)->middleware("demo:PUT|PATCH|DELETE");

                //Interest Controller
                Route::get('interest_calculation/get_last_posting/{account_type_id?}', [InterestController::class, 'get_last_posting'])->name('interest_calculation.get_last_posting');
                Route::match(['get', 'post'], 'interest_calculation/calculator', [InterestController::class, 'calculator'])->name('interest_calculation.calculator');
                Route::post('interest_calculation/posting', [InterestController::class, 'interest_posting'])->name('interest_calculation.interest_posting');

                //Transaction
                Route::get('transactions/get_table_data', [TransactionController::class, 'get_table_data']);
                Route::resource('transactions', TransactionController::class);

                //Get Transaction Categories
                Route::get('transaction_categories/get_category_by_type/{type}', [TransactionCategoryController::class, 'get_category_by_type']);

                //Deposit Requests
                Route::post('deposit_requests/get_table_data', [DepositRequestController::class, 'get_table_data']);
                Route::get('deposit_requests/approve/{id}', [DepositRequestController::class, 'approve'])->name('deposit_requests.approve');
                Route::get('deposit_requests/reject/{id}', [DepositRequestController::class, 'reject'])->name('deposit_requests.reject');
                Route::delete('deposit_requests/{id}', [DepositRequestController::class, 'destroy'])->name('deposit_requests.destroy');
                Route::get('deposit_requests/{id}', [DepositRequestController::class, 'show'])->name('deposit_requests.show');
                Route::get('deposit_requests', [DepositRequestController::class, 'index'])->name('deposit_requests.index');

                //Withdraw Requests
                Route::post('withdraw_requests/get_table_data', [WithdrawRequestController::class, 'get_table_data']);
                Route::get('withdraw_requests/approve/{id}', [WithdrawRequestController::class, 'approve'])->name('withdraw_requests.approve');
                Route::get('withdraw_requests/reject/{id}', [WithdrawRequestController::class, 'reject'])->name('withdraw_requests.reject');
                Route::delete('withdraw_requests/{id}', [WithdrawRequestController::class, 'destroy'])->name('withdraw_requests.destroy');
                Route::get('withdraw_requests/{id}', [WithdrawRequestController::class, 'show'])->name('withdraw_requests.show');
                Route::get('withdraw_requests', [WithdrawRequestController::class, 'index'])->name('withdraw_requests.index');

                //Expense
                Route::get('expenses/get_table_data', [ExpenseController::class, 'get_table_data']);
                Route::resource('expenses', ExpenseController::class);

                //Loan Controller
                Route::get('loans/upcoming_loan_repayments', [LoanController::class, 'upcoming_loan_repayments'])->name('loans.upcoming_loan_repayments');
                Route::post('loans/get_table_data', [LoanController::class, 'get_table_data']);
                Route::get('loans/calculator', [LoanController::class, 'calculator'])->name('loans.admin_calculator');
                Route::post('loans/calculator/calculate', [LoanController::class, 'calculate'])->name('loans.calculate');
                Route::match(['get', 'post'], 'loans/approve/{id}', [LoanController::class, 'approve'])->name('loans.approve');
                Route::get('loans/reject/{id}', [LoanController::class, 'reject'])->name('loans.reject');
                Route::get('loans/filter/{status?}', [LoanController::class, 'index'])->name('loans.filter')->where('status', '[A-Za-z]+');
                Route::resource('loans', LoanController::class);

                //Loan Collateral Controller
                Route::get('loan_collaterals/loan/{loan_id}', [LoanCollateralController::class, 'index'])->name('loan_collaterals.index');
                Route::resource('loan_collaterals', LoanCollateralController::class)->except('index');

                //Loan Guarantor Controller
                Route::resource('guarantors', GuarantorController::class)->except(['show', 'index']);

                //Loan Payment Controller
                Route::get('loan_payments/get_repayment_by_loan_id/{loan_id}', [LoanPaymentController::class, 'get_repayment_by_loan_id']);
                Route::get('loan_payments/get_table_data', [LoanPaymentController::class, 'get_table_data']);
                Route::resource('loan_payments', LoanPaymentController::class);

                //Bank Accounts
				Route::resource('bank_accounts', BankAccountController::class)->middleware("demo:PUT|PATCH|DELETE");

				//Bank Transaction
				Route::get('bank_transactions/get_table_data', [BankTransactionController::class, 'get_table_data']);
				Route::resource('bank_transactions', BankTransactionController::class)->middleware("demo:PUT|PATCH|DELETE");

                //Report Controller
                Route::match(['get', 'post'], 'reports/account_statement', [ReportController::class, 'account_statement'])->name('reports.account_statement');
                Route::match(['get', 'post'], 'reports/account_balances', [ReportController::class, 'account_balances'])->name('reports.account_balances');
                Route::match(['get', 'post'], 'reports/transactions_report', [ReportController::class, 'transactions_report'])->name('reports.transactions_report');
                Route::match(['get', 'post'], 'reports/loan_report', [ReportController::class, 'loan_report'])->name('reports.loan_report');
                Route::get('reports/loan_due_report', [ReportController::class, 'loan_due_report'])->name('reports.loan_due_report');
                Route::match(['get', 'post'], 'reports/loan_repayment_report', [ReportController::class, 'loan_repayment_report'])->name('reports.loan_repayment_report');
                Route::match(['get', 'post'], 'reports/expense_report', [ReportController::class, 'expense_report'])->name('reports.expense_report');
                Route::match(['get', 'post'], 'reports/cash_in_hand', [ReportController::class, 'cash_in_hand'])->name('reports.cash_in_hand');
                Route::match(['get', 'post'], 'reports/bank_transactions', [ReportController::class, 'bank_transactions'])->name('reports.bank_transactions');
				Route::get('reports/bank_balances', [ReportController::class, 'bank_balances'])->name('reports.bank_balances');
                Route::match(['get', 'post'], 'reports/revenue_report', [ReportController::class, 'revenue_report'])->name('reports.revenue_report');
            });

            Route::group(['middleware' => ['customer'], 'prefix' => 'portal'], function () {

                //Membership Details
                Route::get('profile/membership_details', [ProfileController::class, 'membership_details'])->name('profile.membership_details');

                //Transfer Controller
                Route::match(['get', 'post'], 'transfer/own_account_transfer', [App\Http\Controllers\Customer\TransferController::class, 'own_account_transfer'])->name('transfer.own_account_transfer');
                Route::match(['get', 'post'], 'transfer/other_account_transfer', [App\Http\Controllers\Customer\TransferController::class, 'other_account_transfer'])->name('transfer.other_account_transfer');
                Route::get('transfer/transaction_details/{id}', [App\Http\Controllers\Customer\TransferController::class, 'transaction_details'])->name('trasnactions.details');
                Route::get('transfer/get_exchange_amount/{from?}/{to?}/{amount?}', [App\Http\Controllers\Customer\TransferController::class, 'get_exchange_amount'])->name('transfer.get_exchange_amount');
                Route::post('transfer/get_final_amount', [App\Http\Controllers\Customer\TransferController::class, 'get_final_amount'])->name('transfer.get_final_amount');
                Route::get('transfer/transaction_requests', [App\Http\Controllers\Customer\TransferController::class, 'transaction_requests'])->name('trasnactions.transaction_requests');

                //Loan Controller
                Route::match(['get', 'post'], 'loans/calculator', [App\Http\Controllers\Customer\LoanController::class, 'calculator'])->name('loans.calculator');
                Route::get('loans/loan_products', [App\Http\Controllers\Customer\LoanController::class, 'loan_products'])->name('loans.loan_products');
                Route::match(['get', 'post'], 'loans/apply_loan', [App\Http\Controllers\Customer\LoanController::class, 'apply_loan'])->name('loans.apply_loan');
                Route::get('loans/loan_details/{id}', [App\Http\Controllers\Customer\LoanController::class, 'loan_details'])->name('loans.loan_details');
                Route::match(['get', 'post'], 'loans/payment/{loan_id}', [App\Http\Controllers\Customer\LoanController::class, 'loan_payment'])->name('loans.loan_payment');
                Route::get('loans/my_loans', [App\Http\Controllers\Customer\LoanController::class, 'index'])->name('loans.my_loans');

                //Deposit Money
                Route::match(['get', 'post'], 'deposit/manual_deposit/{id}', [App\Http\Controllers\Customer\DepositController::class, 'manual_deposit'])->name('deposit.manual_deposit');
                Route::get('deposit/manual_methods', [App\Http\Controllers\Customer\DepositController::class, 'manual_methods'])->name('deposit.manual_methods');

                //Automatic Deposit
                Route::get('deposit/get_exchange_amount/{from?}/{to?}/{amount?}', [App\Http\Controllers\Customer\DepositController::class, 'get_exchange_amount'])->name('deposit.get_exchange_amount');
                Route::match(['get', 'post'], 'deposit/automatic_deposit/{id}', [App\Http\Controllers\Customer\DepositController::class, 'automatic_deposit'])->name('deposit.automatic_deposit');
                Route::get('deposit/automatic_methods', [App\Http\Controllers\Customer\DepositController::class, 'automatic_methods'])->name('deposit.automatic_methods');

                //Withdraw Money
                Route::match(['get', 'post'], 'withdraw/manual_withdraw/{id}/{otp?}', [App\Http\Controllers\Customer\WithdrawController::class, 'manual_withdraw'])->name('withdraw.manual_withdraw');
                Route::get('withdraw/manual_methods', [App\Http\Controllers\Customer\WithdrawController::class, 'manual_methods'])->name('withdraw.manual_methods');

                //Report Controller
                Route::match(['get', 'post'], 'reports/account_statement', [App\Http\Controllers\Customer\ReportController::class, 'account_statement'])->name('customer_reports.account_statement');
                Route::match(['get', 'post'], 'reports/transactions_report', [App\Http\Controllers\Customer\ReportController::class, 'transactions_report'])->name('customer_reports.transactions_report');
                Route::match(['get', 'post'], 'reports/account_balances', [App\Http\Controllers\Customer\ReportController::class, 'account_balances'])->name('customer_reports.account_balances');

            });

            Route::get('switch_language/', function () {
                if (isset($_GET['language'])) {
                    session(['language' => $_GET['language']]);
                    return back();
                }
            })->name('switch_language');

            Route::get('switch_branch/', function () {
                if (isset($_GET['branch']) && isset($_GET['branch_id'])) {
                    session(['branch' => $_GET['branch'], 'branch_id' => $_GET['branch_id']]);
                } else {
                    request()->session()->forget(['branch', 'branch_id']);
                }
                return back();
            })->name('switch_branch');

        });

    });

});

Route::namespace('Gateway')->prefix('callback')->name('callback.')->group(function () {
    //Fiat Currency
    Route::get('paypal', 'PayPal\ProcessController@callback')->name('PayPal')->middleware('auth');
    Route::post('stripe', 'Stripe\ProcessController@callback')->name('Stripe')->middleware('auth');
    Route::post('razorpay', 'Razorpay\ProcessController@callback')->name('Razorpay')->middleware('auth');
    Route::get('paystack', 'Paystack\ProcessController@callback')->name('Paystack')->middleware('auth');
    Route::get('flutterwave', 'Flutterwave\ProcessController@callback')->name('Flutterwave')->middleware('auth');
    Route::match(['get', 'post'], 'voguepay', 'VoguePay\ProcessController@callback')->name('VoguePay');
    Route::get('mollie', 'Mollie\ProcessController@callback')->name('Mollie')->middleware('auth');
    Route::match(['get', 'post'], 'instamojo', 'Instamojo\ProcessController@callback')->name('Instamojo');

    //Crypto Currency
    Route::get('blockchain', 'BlockChain\ProcessController@callback')->name('BlockChain');
    Route::post('coinpayments', 'CoinPayments\ProcessController@callback')->name('CoinPayments');
});

Route::get('dashboard/json_expense_by_category', [DashboardController::class, 'json_expense_by_category'])->middleware('auth');
Route::get('dashboard/json_deposit_withdraw_analytics/{currency_id?}', [DashboardController::class, 'json_deposit_withdraw_analytics'])->middleware('auth');

//Social Login
Route::get('/login/{provider}', [SocialController::class, 'redirect']);
Route::get('/login/{provider}/callback', [SocialController::class, 'callback']);

//Ajax Select2 Controller
Route::get('ajax/get_table_data', [Select2Controller::class, 'get_table_data']);

Route::get('/installation', 'Install\InstallController@index');
Route::get('install/database', 'Install\InstallController@database');
Route::post('install/process_install', 'Install\InstallController@process_install');
Route::get('install/create_user', 'Install\InstallController@create_user');
Route::post('install/store_user', 'Install\InstallController@store_user');
Route::get('install/system_settings', 'Install\InstallController@system_settings');
Route::post('install/finish', 'Install\InstallController@final_touch');

//Update System
Route::get('system/update/{action?}', 'Install\UpdateController@index');
Route::get('migration/update', 'Install\UpdateController@update_migration');