<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanPenaltyPause;
use App\Services\LoanPenaltyPauseService;
use App\Services\LoanRepaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Grace periods: staff give a client a few days on an installment, starting
 * the day it's entered. Penalty stops accruing on the covered installments
 * until it ends; what had already accrued stays owed. Only a super admin
 * can end one early. See LoanPenaltyPauseService.
 */
class GracePeriodController extends Controller
{
    public function create(Request $request, $id)
    {
        $loan = Loan::findOrFail($id);
        if ($loan->status != 1) {
            return redirect()->route('loans.show', $loan->id)->with('error', _lang('Grace periods can only be given on active loans.'));
        }

        $maxDays    = LoanPenaltyPauseService::maxGraceDays();
        $candidates = LoanPenaltyPauseService::graceCandidates($loan, $maxDays);
        $today      = date('Y-m-d');
        $owed       = collect(LoanRepaymentService::arrears($loan, $today)['installments'])
            ->mapWithKeys(fn ($due) => [$due['repayment']->id => $due]);
        $alert_col  = 'col-lg-8 offset-lg-2';

        return view('backend.grace_period.create', compact('loan', 'maxDays', 'candidates', 'owed', 'today', 'alert_col'));
    }

    public function store(Request $request, $id)
    {
        $maxDays = LoanPenaltyPauseService::maxGraceDays();
        $request->validate([
            'days'             => "required|integer|min:1|max:$maxDays",
            'reason'           => 'required|string|max:500',
            'repayment_ids'    => 'nullable|array',
            'repayment_ids.*'  => 'integer',
            'include_upcoming' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $loan  = Loan::lockForUpdate()->findOrFail($id);
            $pause = LoanPenaltyPauseService::grantGrace(
                $loan, (int) $request->days, $request->reason,
                $request->input('repayment_ids', []), $request->boolean('include_upcoming'),
            );
            DB::commit();
        } catch (InvalidArgumentException $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('loans.show', [$loan->id, 'tab' => 'schedule'])
            ->with('success', _lang('Grace period added: no penalty until') . ' ' . $pause->ends_on->format(get_date_format()));
    }

    /** Super admin only (route group). */
    public function end(Request $request, $id)
    {
        $pause = LoanPenaltyPause::where('type', LoanPenaltyPause::GRACE)->findOrFail($id);
        $loan  = Loan::findOrFail($pause->loan_id);

        DB::beginTransaction();
        try {
            LoanPenaltyPauseService::endGrace($pause);
            DB::commit();
        } catch (InvalidArgumentException $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('loans.show', [$loan->id, 'tab' => 'schedule'])
            ->with('success', _lang('Grace period ended: penalty runs again from today.'));
    }

    /** Report: grace periods, current ones first. */
    public function index(Request $request)
    {
        $status = $request->get('status', 'active');
        $today  = Carbon::today()->toDateString();

        $graces = LoanPenaltyPause::where('type', LoanPenaltyPause::GRACE)
            // Only loans this user can see (branch / loan domain scopes).
            ->whereIn('loan_id', Loan::select('id'))
            ->with(['loan.borrower', 'loan.loan_product', 'repayments', 'created_by', 'ended_by_user'])
            ->when($status == 'active', fn ($q) => $q->where('starts_on', '<=', $today)->where('ends_on', '>=', $today))
            ->when($status == 'ended', fn ($q) => $q->where(fn ($q) => $q->where('ends_on', '<', $today)->orWhereColumn('ends_on', '<', 'starts_on')))
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('backend.grace_period.index', compact('graces', 'status'));
    }
}
