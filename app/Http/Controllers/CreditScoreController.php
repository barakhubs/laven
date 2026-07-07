<?php

namespace App\Http\Controllers;

use App\Models\LoanCreditScore;
use App\Models\Loan;
use App\Utilities\CreditScoreCalculator;
use Illuminate\Http\Request;

class CreditScoreController extends Controller {

    public function index(Request $request) {
        $member_no  = $request->member_no ?? '';
        $loan_type  = $request->loan_type ?? '';
        $loan_status = $request->loan_status ?? '';
        $rating     = $request->rating ?? '';
        $min_score  = $request->min_score ?? '';
        $max_score  = $request->max_score ?? '';
        $overdue_only = $request->overdue_only ?? '';
        $sort_by    = $request->sort_by ?: 'score';
        $sort_order = $request->sort_order ?: 'asc';

        $ratingRanges = [
            'excellent' => [90, 100],
            'good'      => [75, 89.99],
            'fair'      => [60, 74.99],
            'poor'      => [40, 59.99],
            'very_poor' => [0, 39.99],
        ];

        $data['report_data'] = LoanCreditScore::with(['loan.borrower', 'loan.loan_product'])
            ->whereHas('loan', function ($query) use ($loan_type, $loan_status, $member_no) {
                $query->when($loan_type, fn($q) => $q->where('loan_product_id', $loan_type))
                    ->when($loan_status !== '', fn($q) => $q->where('status', $loan_status))
                    ->when($member_no, function ($q) use ($member_no) {
                        $q->whereHas('borrower', function ($q2) use ($member_no) {
                            $q2->where('member_no', 'like', "%$member_no%")
                                ->orWhere('first_name', 'like', "%$member_no%")
                                ->orWhere('last_name', 'like', "%$member_no%");
                        });
                    });
            })
            ->when($rating && isset($ratingRanges[$rating]), function ($q) use ($rating, $ratingRanges) {
                [$min, $max] = $ratingRanges[$rating];
                return $q->whereBetween('score', [$min, $max]);
            })
            ->when($min_score !== '', fn($q) => $q->where('score', '>=', (float) $min_score))
            ->when($max_score !== '', fn($q) => $q->where('score', '<=', (float) $max_score))
            ->when($overdue_only, fn($q) => $q->where('overdue_count', '>', 0))
            ->orderBy($sort_by, $sort_order)
            ->paginate(25)
            ->appends($request->query());

        $data += compact(
            'member_no', 'loan_type', 'loan_status', 'rating',
            'min_score', 'max_score', 'overdue_only', 'sort_by', 'sort_order'
        );

        return view('backend.reports.credit_score_report', $data);
    }

    public function recalculate($loan_id) {
        $loan = Loan::findOrFail($loan_id);
        CreditScoreCalculator::recalculate($loan);

        return back()->with('success', _lang('Credit score recalculated'));
    }

    public function recalculate_all() {
        CreditScoreCalculator::recalculateAll();

        return back()->with('success', _lang('All credit scores have been queued for recalculation'));
    }

}

