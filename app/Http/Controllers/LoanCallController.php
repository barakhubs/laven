<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanCall;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Logging calls to clients from Due Payments. The lock is per client, not
 * per loan, so a client with two late loans is only called once. Admins can
 * log a call on a locked client; staff can't.
 */
class LoanCallController extends Controller
{
    public function create(Request $request, $id)
    {
        $loan    = Loan::findOrFail($id);
        $member  = Member::withoutGlobalScopes()->findOrFail($loan->borrower_id);
        $calls   = LoanCall::with(['created_by', 'loan'])->where('member_id', $member->id)->orderByDesc('id')->limit(5)->get();
        $latest  = $calls->first();
        $blocked = $latest && $latest->isLocked() && ! auth()->user()->isAdmin();

        return view('backend.loan_call.modal.create', compact('loan', 'member', 'calls', 'latest', 'blocked'));
    }

    public function store(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'outcome'        => ['required', Rule::in(array_keys(LoanCall::outcomes()))],
            'promise_date'   => 'required_if:outcome,' . LoanCall::PROMISED . '|nullable|date|after_or_equal:today|before_or_equal:' . now()->addDays(30)->toDateString(),
            'promise_amount' => 'nullable|numeric|min:0',
            'note'           => 'nullable|string|max:1000',
        ], [
            'promise_date.required_if'     => _lang('Enter the date the client promised to pay.'),
            'promise_date.before_or_equal' => _lang('The promise date must be within 30 days.'),
        ]);

        if ($validator->fails()) {
            return $this->reply($request, 'error', $validator->errors()->all());
        }

        $loan = Loan::findOrFail($id);

        DB::beginTransaction();
        // Lock the client's row so two calls logged at the same moment queue up.
        Member::withoutGlobalScopes()->lockForUpdate()->findOrFail($loan->borrower_id);
        $latest = LoanCall::with('created_by')->where('member_id', $loan->borrower_id)->orderByDesc('id')->first();

        if ($latest && $latest->isLocked() && ! auth()->user()->isAdmin()) {
            DB::rollBack();
            return $this->reply($request, 'error', _lang('Already called by') . ' ' . $latest->created_by->name . ' ' . $latest->created_at->diffForHumans()
                . '. ' . _lang('Next call from') . ' ' . $latest->nextCallLabel() . '.');
        }

        $promised = $request->outcome === LoanCall::PROMISED;

        $call                  = new LoanCall();
        $call->loan_id         = $loan->id;
        $call->member_id       = $loan->borrower_id;
        $call->outcome         = $request->outcome;
        $call->promise_date    = $promised ? $request->promise_date : null;
        $call->promise_amount  = $promised ? $request->promise_amount : null;
        $call->note            = $request->note;
        $call->locked_until    = LoanCall::lockUntil($call->outcome, $call->promise_date);
        $call->created_user_id = auth()->id();
        $call->save();
        DB::commit();

        return $this->reply($request, 'success', _lang('Call saved. Next call from') . ' ' . $call->nextCallLabel() . '.');
    }

    private function reply(Request $request, string $result, $message)
    {
        if ($request->ajax()) {
            return response()->json(['result' => $result, 'action' => 'store', 'message' => $message]);
        }

        return back()->with($result, is_array($message) ? implode(' ', $message) : $message)->withInput();
    }
}
