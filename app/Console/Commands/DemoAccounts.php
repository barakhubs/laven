<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LoanReserveService;
use App\Services\LoanRepaymentService;
use App\Utilities\LoanCalculator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Demo client accounts for app store reviewers and demos.
 *
 * Everything lives in its own "Demo Branch" with its own (inactive) demo loan and savings
 * products and DEMO- numbers, so real numbering is untouched and --remove deletes it all.
 * Loans are scheduled with the same LoanCalculator and paid with the same
 * LoanRepaymentService as real ones. No email, SMS or push is sent.
 *
 *   php artisan laven:demo-accounts --password='...'
 *   php artisan laven:demo-accounts --remove
 */
class DemoAccounts extends Command
{
    protected $signature = 'laven:demo-accounts {--password= : Password for every demo login} {--remove : Delete all demo data instead}';

    protected $description = 'Create (or remove) 10 demo clients with savings, completed and active loans, and transactions';

    private const EMAIL_DOMAIN = 'demo.lavensolutions.co.ug';
    private const BRANCH = 'Demo Branch';

    private const CLIENTS = [
        // first, last, gender, savings, completed loan, active loan, months into active loan, overdue?
        ['Grace', 'Akello', 'female', 450000, 1000000, 2000000, 3, false],
        ['Joseph', 'Okello', 'male', 820000, 2000000, 3000000, 2, false],
        ['Sarah', 'Nakato', 'female', 260000, 500000, 1500000, 4, true],
        ['Peter', 'Mugisha', 'male', 1200000, 3000000, 5000000, 1, false],
        ['Esther', 'Atim', 'female', 180000, 800000, 1000000, 3, true],
        ['David', 'Ssemakula', 'male', 640000, 1500000, 2500000, 2, false],
        ['Agnes', 'Draru', 'female', 350000, 1000000, 0, 0, false],
        ['Moses', 'Ojok', 'male', 900000, 0, 4000000, 5, false],
        ['Ruth', 'Anyango', 'female', 220000, 600000, 1200000, 2, true],
        ['Isaac', 'Wandera', 'male', 1500000, 2500000, 3500000, 4, false],
    ];

    public function handle(): int
    {
        if ($this->option('remove')) {
            return $this->remove();
        }

        $password = (string) $this->option('password');
        if (strlen($password) < 6) {
            $this->error('Give a password of at least 6 characters: --password=\'...\'');
            return self::FAILURE;
        }
        if (User::where('email', 'like', '%@' . self::EMAIL_DOMAIN)->exists()) {
            $this->error('Demo accounts already exist. Run with --remove first to recreate them.');
            return self::FAILURE;
        }

        // Records are attributed to the first super admin, as if they had entered them.
        $admin = User::where('user_type', 'superadmin')->orderBy('id')->first() ?? User::where('user_type', 'admin')->orderBy('id')->first();
        if (! $admin) {
            $this->error('No admin user found to attribute the records to.');
            return self::FAILURE;
        }
        Auth::setUser($admin);
        app()->instance('loan_domain_scope', 'main');
        // Demo products and schedule rows are set field by field below; the models guard bulk assignment.
        Model::unguard();

        DB::transaction(function () use ($password) {
            $currencyId = base_currency_id();
            $branch = Branch::firstOrCreate(['name' => self::BRANCH], ['descriptions' => 'Demo accounts for app reviewers. Not real clients.']);

            $savingsProduct = SavingsProduct::firstOrCreate(['name' => 'Demo Savings'], [
                'account_number_prefix' => 'DEMO-SAV-', 'starting_account_number' => 1, 'currency_id' => $currencyId,
                'interest_rate' => 0, 'allow_withdraw' => 1, 'minimum_account_balance' => 0, 'minimum_deposit_amount' => 0,
                'maintenance_fee' => 0, 'auto_create' => 0, 'status' => 0,
            ]);
            $loanProduct = LoanProduct::firstOrCreate(['name' => 'Demo Business Loan'], [
                'loan_id_prefix' => 'DEMO-LN-', 'starting_loan_id' => 1, 'minimum_amount' => 100000, 'maximum_amount' => 10000000,
                'late_payment_penalties' => 5, 'interest_rate' => 10, 'interest_type' => 'flat_rate', 'term' => 6,
                'term_period' => '+1 month', 'status' => 0, 'requires_savings_reserve' => 1,
            ]);

            foreach (self::CLIENTS as $i => [$first, $last, $gender, $savings, $completed, $active, $monthsIn, $overdue]) {
                $n = $i + 1;
                $email = "demo{$n}@" . self::EMAIL_DOMAIN;
                $joined = Carbon::now()->subMonths(12)->addDays($n * 3)->setTime(9, 30);

                $user = new User();
                $user->name = "$first $last";
                $user->email = $email;
                $user->password = Hash::make($password);
                $user->user_type = 'customer';
                $user->status = 1;
                $user->email_verified_at = now();
                $user->save();

                $member = new Member();
                $member->first_name = $first;
                $member->last_name = $last;
                $member->user_id = $user->id;
                $member->branch_id = $branch->id;
                $member->email = $email;
                $member->country_code = '256';
                $member->mobile = '+2567000000' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
                $member->member_no = 'DEMO-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
                $member->nin = 'CM' . str_pad((string) (90000000 + $n), 8, '0', STR_PAD_LEFT) . 'DEMO';
                $member->gender = $gender;
                $member->city = 'Arua';
                $member->address = 'Arua, Uganda';
                $member->business_name = "$last Enterprises";
                $member->status = 1;
                $member->save();
                DB::table('members')->where('id', $member->id)->update(['created_at' => $joined]);

                $account = new SavingsAccount();
                $account->account_number = 'DEMO-SAV-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
                $account->member_id = $member->id;
                $account->savings_product_id = $savingsProduct->id;
                $account->status = 1;
                $account->opening_balance = 0;
                $account->description = 'Demo account';
                $account->save();

                // Savings: an opening deposit and a few top-ups since.
                $this->credit($member, $account, $branch, $joined, round($savings * 0.5), 'Deposit', 'Cash', 'Initial deposit');
                $this->credit($member, $account, $branch, $joined->copy()->addMonths(4)->setTime(15, 10), round($savings * 0.3), 'Deposit', 'MTN Mobile Money', 'Deposit via MTN Mobile Money');
                $this->credit($member, $account, $branch, Carbon::now()->subDays(6 + $n)->setTime(11, 45), round($savings * 0.2), 'Deposit', 'Airtel Money', 'Deposit via Airtel Money');

                if ($completed > 0) {
                    $loan = $this->loan($member, $account, $branch, $loanProduct, $completed, $joined->copy()->addMonths(1));
                    $this->payInstallments($loan, PHP_INT_MAX);
                }
                if ($active > 0) {
                    $release = Carbon::now()->subMonths($monthsIn)->subDays(5);
                    $loan = $this->loan($member, $account, $branch, $loanProduct, $active, $release);
                    // Pay what has fallen due, except the latest one for clients shown as behind.
                    $due = LoanRepayment::where('loan_id', $loan->id)->where('repayment_date', '<', now()->toDateString())->count();
                    $this->payInstallments($loan, $overdue ? max(0, $due - 1) : $due);
                }

                $this->line("  {$member->member_no}  {$email}  {$first} {$last}");
            }
        });

        $this->info('Created 10 demo clients in "' . self::BRANCH . '". Remove them with: php artisan laven:demo-accounts --remove');
        $this->warn('They count in admin totals (overview, collections, financial summary) until removed.');
        return self::SUCCESS;
    }

    private function credit(Member $member, SavingsAccount $account, Branch $branch, Carbon $when, float $amount, string $type, string $method, string $description, ?int $loanId = null): void
    {
        $tx = new Transaction();
        $tx->trans_date = $when;
        $tx->member_id = $member->id;
        $tx->savings_account_id = $account->id;
        $tx->amount = $amount;
        $tx->dr_cr = 'cr';
        $tx->type = $type;
        $tx->method = $method;
        $tx->status = 2;
        $tx->description = $description;
        $tx->created_user_id = Auth::id();
        $tx->branch_id = $branch->id;
        $tx->loan_id = $loanId;
        $tx->save();
    }

    /** Creates and disburses a loan as LoanController@approve does: schedule, 70% paid out, 30% reserve. */
    private function loan(Member $member, SavingsAccount $account, Branch $branch, LoanProduct $product, float $amount, Carbon $release): Loan
    {
        $loan = new Loan();
        $loan->loan_id = $product->loan_id_prefix . str_pad((string) $product->starting_loan_id, 4, '0', STR_PAD_LEFT);
        $loan->loan_product_id = $product->id;
        $loan->borrower_id = $member->id;
        $loan->currency_id = base_currency_id();
        $loan->release_date = $release->toDateString();
        $loan->first_payment_date = $release->copy()->addMonth()->toDateString();
        $loan->applied_amount = $amount;
        $loan->late_payment_penalties = $product->late_payment_penalties;
        $loan->description = 'Demo loan';
        $loan->status = 1;
        $loan->approved_date = $release->toDateString();
        $loan->approved_user_id = Auth::id();
        $loan->created_user_id = Auth::id();
        $loan->branch_id = $branch->id;
        $loan->debit_account_id = $account->id;
        $loan->disburse_method = 'account';
        $loan->save();
        $product->increment('starting_loan_id');

        $calc = new LoanCalculator($amount, $loan->first_payment_date, $product->interest_rate, $product->term, $product->term_period, $loan->late_payment_penalties);
        foreach ($calc->get_flat_rate() as $r) {
            LoanRepayment::create([
                'loan_id' => $loan->id, 'repayment_date' => $r['date'], 'amount_to_pay' => $r['amount_to_pay'],
                'penalty' => $r['penalty'], 'principal_amount' => $r['principal_amount'], 'interest' => $r['interest'], 'balance' => $r['balance'],
            ]);
        }
        $loan->total_payable = $calc->payable_amount;
        $loan->save();

        $reserve = LoanReserveService::target($loan);
        $when = $release->copy()->setTime(11, 20);
        $this->credit($member, $account, $branch, $when, $amount - $reserve, 'Loan', 'Manual', 'Loan approved and disbursed', $loan->id);
        if ($reserve > 0) {
            $this->credit($member, $account, $branch, $when, $reserve, 'loan_savings', 'Manual', '30% loan savings deposit', $loan->id);
        }
        return $loan;
    }

    /** Pays the oldest $count installments in cash, each two days before it was due. */
    private function payInstallments(Loan $loan, int $count): void
    {
        for ($k = 0; $k < $count; $k++) {
            $next = LoanRepayment::where('loan_id', $loan->id)->where('status', 0)->orderBy('id')->first();
            if (! $next) {
                return;
            }
            $paidAt = Carbon::parse($next->getRawOriginal('repayment_date'))->subDays(2)->toDateString();
            $owed = LoanRepaymentService::arrears($loan->fresh(), $paidAt)['installments'][0]['total'] ?? 0;
            if ($owed <= 0) {
                return;
            }
            LoanRepaymentService::apply($loan->fresh(), $owed, $paidAt, null, ['remarks' => 'Mobile money']);
        }
    }

    private function remove(): int
    {
        $members = Member::withoutGlobalScopes()->where('member_no', 'like', 'DEMO-%')->pluck('id');
        $users = User::where('email', 'like', '%@' . self::EMAIL_DOMAIN)->pluck('id');
        $loans = Loan::withoutGlobalScopes()->whereIn('borrower_id', $members)->pluck('id');

        DB::transaction(function () use ($members, $users, $loans) {
            $payments = DB::table('loan_payments')->whereIn('loan_id', $loans)->pluck('id');
            DB::table('loan_payment_allocations')->whereIn('loan_payment_id', $payments)->delete();
            DB::table('loan_payments')->whereIn('id', $payments)->delete();
            DB::table('loan_repayments')->whereIn('loan_id', $loans)->delete();
            DB::table('transactions')->whereIn('member_id', $members)->delete();
            DB::table('loans')->whereIn('id', $loans)->delete();
            DB::table('savings_accounts')->whereIn('member_id', $members)->delete();
            DB::table('members')->whereIn('id', $members)->delete();
            DB::table('notifications')->whereIn('notifiable_id', $members)->where('notifiable_type', Member::class)->delete();
            DB::table('personal_access_tokens')->whereIn('tokenable_id', $users)->where('tokenable_type', User::class)->delete();
            if (\Schema::hasTable('device_tokens')) {
                DB::table('device_tokens')->whereIn('user_id', $users)->delete();
            }
            DB::table('users')->whereIn('id', $users)->delete();
            LoanProduct::where('name', 'Demo Business Loan')->delete();
            SavingsProduct::where('name', 'Demo Savings')->delete();
            Branch::where('name', self::BRANCH)->delete();
        });

        $this->info("Removed {$members->count()} demo clients, {$loans->count()} loans and their records.");
        return self::SUCCESS;
    }
}
