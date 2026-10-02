<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\MemberController;
use App\Models\CustomField;
use Illuminate\Http\Request;

class MemberProfileController extends ApiController
{
    public function __construct()
    {
        \App\Utilities\Overrider::load('Settings');
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    /**
     * GET /v1/profile
     * The member's profile as the web portal's "My Profile" page shows it:
     * details, financial summary, per-loan breakdown and KYC documents.
     */
    public function show(Request $request)
    {
        $member = $request->attributes->get('effectiveMember');
        $data   = MemberController::profileData($member);

        $customFields = CustomField::where('table', 'members')->where('status', 1)->orderBy('id')->get();
        $values       = json_decode($member->custom_fields ?? '', true) ?: [];

        $loans = $data['loans']->map(fn($loan) => [
            'id'             => $loan->id,
            'loan_id'        => $loan->loan_id ?: '#' . $loan->id,
            'product_name'   => $loan->loan_product->name ?? 'N/A',
            'currency'       => $loan->currency->name ?? get_option('currency'),
            'applied_amount' => (float) $loan->applied_amount,
            'principal_paid' => (float) $loan->total_paid,
            'interest_paid'  => (float) $loan->payments->sum('interest'),
            'penalties_paid' => (float) $loan->payments->sum('late_penalties'),
            'balance_due'    => (float) $loan->remaining_balance,
            'status'         => (int) $loan->status,
        ])->values();

        return $this->success([
            'member' => [
                'id'            => $member->id,
                'member_no'     => $member->member_no,
                'name'          => trim($member->first_name . ' ' . $member->last_name),
                'first_name'    => $member->first_name,
                'last_name'     => $member->last_name,
                'business_name' => $member->business_name,
                'branch'        => $member->branch->name ?? null,
                'loan_officer'  => $member->loan_officer->name ?? null,
                'email'         => $member->email,
                'mobile'        => $member->mobile,
                'nin'           => $member->nin,
                'gender'        => $member->gender ? ucwords($member->gender) : null,
                'city'          => $member->city,
                'state'         => $member->state,
                'zip'           => $member->zip,
                'address'       => $member->address,
                'credit_source' => $member->credit_source,
                'member_since'  => $member->created_at,
                'active'        => (int) $member->status === 1,
                'photo'         => $this->memberPhotoUrl($member, $member->user),
                'custom_fields' => $customFields->map(function ($field) use ($values) {
                    $value  = $values[$field->field_name]['field_value'] ?? null;
                    $isFile = $field->field_type == 'file';
                    return [
                        'label' => $field->field_name,
                        'value' => $isFile ? null : $value,
                        'url'   => $isFile && $value ? asset('uploads/media/' . $value) : null,
                    ];
                })->values(),
            ],
            'summary' => [
                'currency'               => get_option('currency'),
                'total_savings'          => round((float) $data['totalSavingsBalance'], 2),
                'savings_accounts'       => $data['savingsAccounts']->count(),
                'active_loan_balance'    => round((float) $data['totalLoanDue'], 2),
                'total_loan_applied'     => (float) $data['totalLoanApplied'],
                'total_principal_repaid' => (float) $data['totalLoanPaid'],
                'total_interest_paid'    => (float) $data['totalInterestPaid'],
                'total_penalties_paid'   => (float) $data['totalPenaltiesPaid'],
                'loans_total'            => $data['loans']->count(),
                'loans_active'           => $data['activeLoans'],
                'loans_completed'        => $data['completedLoans'],
                'loans_pending'          => $data['pendingLoans'],
            ],
            'loans'     => $loans,
            'documents' => $member->documents->map(fn($doc) => [
                'id'           => $doc->id,
                'name'         => $doc->name,
                'url'          => asset('uploads/documents/' . $doc->document),
                'submitted_at' => date('Y-m-d H:i:s', strtotime($doc->getRawOriginal('created_at'))),
            ])->values(),
        ], 'Profile loaded.');
    }
}
