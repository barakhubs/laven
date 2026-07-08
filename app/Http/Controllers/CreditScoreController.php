<?php

namespace App\Http\Controllers;

use App\Models\LoanCreditScore;
use App\Models\Loan;
use App\Utilities\CreditScoreCalculator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CreditScoreController extends Controller {

    protected $ratingRanges = [
        'excellent' => [90, 100],
        'good'      => [75, 89.99],
        'fair'      => [60, 74.99],
        'poor'      => [40, 59.99],
        'very_poor' => [0, 39.99],
    ];

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

        $view_mode = $request->view_mode === 'member' ? 'member' : 'loan';

        $sortable_loan   = ['score', 'overdue_count', 'late_count', 'last_calculated_at'];
        $sortable_member = ['score', 'overdue_count', 'late_count', 'last_calculated_at', 'loan_count'];
        $sortable = $view_mode === 'member' ? $sortable_member : $sortable_loan;
        if (!in_array($sort_by, $sortable)) {
            $sort_by = 'score';
        }

        $allowed_per_page = [10, 25, 50, 100, 250];
        $per_page = (int) ($request->per_page ?: 25);
        if (!in_array($per_page, $allowed_per_page)) {
            $per_page = 25;
        }

        // Base query: every loan-level credit score row matching the
        // member/loan-type/loan-status filters. Used as the source for
        // both view modes so the two stay in sync with each other.
        $base_query = LoanCreditScore::with(['loan.borrower', 'loan.loan_product'])
            ->whereHas('loan', function ($query) use ($loan_type, $loan_status, $member_no) {
                $query->when($loan_type, fn ($q) => $q->where('loan_product_id', $loan_type))
                    ->when($loan_status !== '', fn ($q) => $q->where('status', $loan_status))
                    ->when($member_no, function ($q) use ($member_no) {
                        $q->whereHas('borrower', function ($q2) use ($member_no) {
                            $q2->where('member_no', 'like', "%$member_no%")
                                ->orWhere('first_name', 'like', "%$member_no%")
                                ->orWhere('last_name', 'like', "%$member_no%");
                        });
                    });
            });

        if ($view_mode === 'member') {
            $data['report_data'] = $this->buildMemberLevelData(
                (clone $base_query)->get(),
                $rating, $min_score, $max_score, $overdue_only,
                $sort_by, $sort_order, $per_page, $request
            );
        } else {
            $data['report_data'] = (clone $base_query)
                ->when($rating && isset($this->ratingRanges[$rating]), function ($q) use ($rating) {
                    [$min, $max] = $this->ratingRanges[$rating];
                    return $q->whereBetween('score', [$min, $max]);
                })
                ->when($min_score !== '', fn ($q) => $q->where('score', '>=', (float) $min_score))
                ->when($max_score !== '', fn ($q) => $q->where('score', '<=', (float) $max_score))
                ->when($overdue_only, fn ($q) => $q->where('overdue_count', '>', 0))
                ->orderBy($sort_by, $sort_order)
                ->paginate($per_page)
                ->appends($request->query());
        }

        $data += compact(
            'member_no', 'loan_type', 'loan_status', 'rating',
            'min_score', 'max_score', 'overdue_only', 'sort_by', 'sort_order',
            'per_page', 'view_mode'
        );

        return view('backend.reports.credit_score_report', $data);
    }

    /**
     * Roll loan-level scores up into one row per member.
     *
     * The member score is a loan-amount-weighted average: a big loan going
     * bad should move a member's score more than a small one going bad.
     * Falls back to a simple average if none of their loans have an amount
     * on record.
     */
    private function buildMemberLevelData($rows, $rating, $min_score, $max_score, $overdue_only, $sort_by, $sort_order, $per_page, $request) {
        $grouped = $rows->groupBy('borrower_id')->map(function ($group) {
            $totalWeight = $group->sum(fn ($row) => (float) ($row->loan->applied_amount ?? 0));

            if ($totalWeight > 0) {
                $weightedScore = $group->sum(fn ($row) => $row->score * ($row->loan->applied_amount ?? 0)) / $totalWeight;
            } else {
                $weightedScore = $group->avg('score');
            }

            $weightedScore = round($weightedScore, 2);

            return (object) [
                'borrower_id'    => $group->first()->borrower_id,
                'borrower'       => $group->first()->loan->borrower,
                'score'          => $weightedScore,
                'loan_count'     => $group->count(),
                'on_time_count'  => $group->sum('on_time_count'),
                'late_count'     => $group->sum('late_count'),
                'overdue_count'  => $group->sum('overdue_count'),
                'total_schedules' => $group->sum('total_schedules'),
                'last_calculated_at' => $group->max('last_calculated_at'),
                'rating'         => $this->ratingLabel($weightedScore),
                'rating_color'   => $this->ratingColor($weightedScore),
            ];
        })->values();

        // Apply rating/score/overdue filters against the rolled-up member score
        $filtered = $grouped
            ->when($rating && isset($this->ratingRanges[$rating]), function ($col) use ($rating) {
                [$min, $max] = $this->ratingRanges[$rating];
                return $col->whereBetween('score', [$min, $max]);
            })
            ->when($min_score !== '', fn ($col) => $col->where('score', '>=', (float) $min_score))
            ->when($max_score !== '', fn ($col) => $col->where('score', '<=', (float) $max_score))
            ->when($overdue_only, fn ($col) => $col->where('overdue_count', '>', 0));

        $sorted = $sort_order === 'desc'
            ? $filtered->sortByDesc($sort_by)->values()
            : $filtered->sortBy($sort_by)->values();

        $page = LengthAwarePaginator::resolveCurrentPage() ?: 1;
        $items = $sorted->slice(($page - 1) * $per_page, $per_page)->values();

        return new LengthAwarePaginator(
            $items,
            $sorted->count(),
            $per_page,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    private function ratingLabel($score) {
        if ($score >= 90) return 'Excellent';
        if ($score >= 75) return 'Good';
        if ($score >= 60) return 'Fair';
        if ($score >= 40) return 'Poor';
        return 'Very Poor';
    }

    private function ratingColor($score) {
        if ($score >= 90) return 'success';
        if ($score >= 75) return 'info';
        if ($score >= 60) return 'warning';
        return 'danger';
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