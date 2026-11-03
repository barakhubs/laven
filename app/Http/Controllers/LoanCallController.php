<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanCall;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Logging calls to clients from any client list (?loan_id= or ?member_id=).
 *
 * A call about one of the client's active loans is a collection call: the
 * lock is per client, not per loan, so a client with two late loans is only
 * chased once; admins can log one on a locked client, staff can't. A call
 * with no loan is a general call: always allowed, never locks.
 *
 * Either can say when to call the client again (date, optional time). That
 * becomes the lock, so the client stays greyed out until then: on a
 * collection call in place of the outcome's usual wait, and a general call
 * with a date locks too. "Call back later" needs one.
 */
class LoanCallController extends Controller
{
    public function create(Request $request)
    {
        [$member, $loan] = $this->subject($request);

        $loans   = $this->activeLoans($member);
        $calls   = LoanCall::with(['created_by', 'loan'])->where('member_id', $member->id)->orderByDesc('id')->limit(5)->get();
        $lock    = LoanCall::lockingCallFor($member->id);
        $lock    = $lock && $lock->isLocked() ? $lock : null;
        $blocked = $lock && ! auth()->user()->isAdmin();

        // About the loan it was opened from, else the client's only/oldest active loan.
        $selected = $loan && $loans->contains('id', $loan->id) ? $loan->id : optional($loans->first())->id;
        if ($blocked || ! $selected) {
            $selected = '';
        }

        return view('backend.loan_call.modal.create', compact('member', 'loans', 'calls', 'lock', 'blocked', 'selected'));
    }

    public function store(Request $request)
    {
        $member = $this->member($request->input('member_id'));
        $loanId = $request->filled('loan_id') ? (int) $request->input('loan_id') : null;
        $type   = $loanId ? LoanCall::COLLECTION : LoanCall::GENERAL;

        $validator = Validator::make($request->all(), [
            'loan_id'        => ['nullable', Rule::in($this->activeLoans($member)->pluck('id')->all())],
            'outcome'        => ['required', Rule::in(array_keys(LoanCall::outcomes($type)))],
            'promise_date'   => 'required_if:outcome,' . LoanCall::PROMISED . '|nullable|date|after_or_equal:today|before_or_equal:' . now()->addDays(30)->toDateString(),
            'promise_amount' => 'nullable|numeric|min:0',
            'note'           => 'nullable|string|max:1000',
            'call_back_date' => 'required_if:outcome,' . LoanCall::CALL_BACK . '|nullable|date|after_or_equal:today|before_or_equal:' . now()->addDays(30)->toDateString(),
            'call_back_time' => 'nullable|date_format:H:i',
        ], [
            'loan_id.in'                   => _lang('Choose one of the client\'s active loans, or General.'),
            'promise_date.required_if'     => _lang('Enter the date the client promised to pay.'),
            'promise_date.before_or_equal' => _lang('The promise date must be within 30 days.'),
            'call_back_date.required_if'     => _lang('Choose when to call the client again.'),
            'call_back_date.after_or_equal'  => _lang('The call again date can\'t be in the past.'),
            'call_back_date.before_or_equal' => _lang('The call again date must be within 30 days.'),
        ]);

        // Midnight when no time is given: the client unlocks at the start of that day.
        $callBack = null;
        if ($request->filled('call_back_date')) {
            try {
                $callBack = Carbon::parse($request->call_back_date . ' ' . ($request->filled('call_back_time') ? $request->call_back_time : '00:00'));
            } catch (\Exception $e) {
                // Bad input: the rules above report it.
            }
        }
        $validator->after(function ($v) use ($callBack, $request) {
            if ($callBack && $callBack->isToday() && ! $callBack->isFuture()) {
                $v->errors()->add('call_back_time', $request->filled('call_back_time')
                    ? _lang('The call again time has already passed.')
                    : _lang('To call again today, pick a time.'));
            }
        });

        if ($validator->fails()) {
            return $this->reply($request, 'error', $validator->errors()->all());
        }

        DB::beginTransaction();
        // Lock the client's row so two calls logged at the same moment queue up.
        Member::withoutGlobalScopes()->lockForUpdate()->findOrFail($member->id);

        if ($type === LoanCall::COLLECTION) {
            $lock = LoanCall::lockingCallFor($member->id);
            if ($lock && $lock->isLocked() && ! auth()->user()->isAdmin()) {
                DB::rollBack();
                return $this->reply($request, 'error', _lang('Already called by') . ' ' . $lock->created_by->name . ' ' . $lock->created_at->diffForHumans()
                    . '. ' . _lang('Next call from') . ' ' . $lock->nextCallLabel() . '. ' . _lang('You can still log a General call.'));
            }
        }

        // Staff who are locked out can't bring the next call forward with a general call.
        if ($type === LoanCall::GENERAL && $callBack) {
            $lock = LoanCall::lockingCallFor($member->id);
            if ($lock && $lock->isLocked() && ! auth()->user()->isAdmin() && $callBack->lt($lock->locked_until)) {
                DB::rollBack();
                return $this->reply($request, 'error', _lang('Already called by') . ' ' . $lock->created_by->name . '. '
                    . _lang('Choose a call again date from') . ' ' . $lock->nextCallLabel() . ' ' . _lang('or leave it blank.'));
            }
        }

        $promised = $request->outcome === LoanCall::PROMISED;

        $call                  = new LoanCall();
        $call->loan_id         = $loanId;
        $call->member_id       = $member->id;
        $call->type            = $type;
        $call->outcome         = $request->outcome;
        $call->promise_date    = $promised ? $request->promise_date : null;
        $call->promise_amount  = $promised ? $request->promise_amount : null;
        $call->note            = $request->note;
        $call->call_back_at    = $callBack;
        $call->locked_until    = $callBack ?? ($type === LoanCall::COLLECTION ? LoanCall::lockUntil($call->outcome, $call->promise_date) : null);
        $call->created_user_id = auth()->id();
        $call->save();
        DB::commit();

        return $this->reply($request, 'success', $call->locked_until
            ? _lang('Call saved. Next call from') . ' ' . $call->nextCallLabel() . '.'
            : _lang('Call saved.'));
    }

    /** The client (and loan, if opened from one) the call is for. */
    private function subject(Request $request): array
    {
        if ($request->filled('loan_id')) {
            $loan = Loan::findOrFail($request->input('loan_id'));
            return [$this->member($loan->borrower_id), $loan];
        }

        return [$this->member($request->input('member_id')), null];
    }

    /** Branch-scoped, so staff only reach their own branch's clients (inactive ones too). */
    private function member($id): Member
    {
        return Member::withoutGlobalScope('status')->findOrFail($id);
    }

    private function activeLoans(Member $member)
    {
        return Loan::withoutGlobalScopes()->with('currency')->where('borrower_id', $member->id)->where('status', 1)->orderBy('id')->get();
    }

    private function reply(Request $request, string $result, $message)
    {
        if ($request->ajax()) {
            return response()->json(['result' => $result, 'action' => 'store', 'message' => $message]);
        }

        return back()->with($result, is_array($message) ? implode(' ', $message) : $message)->withInput();
    }
}
