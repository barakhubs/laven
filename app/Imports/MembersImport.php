<?php
namespace App\Imports;

use App\Models\Branch;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class MembersImport implements ToCollection, WithStartRow
{

    private $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {

        $branches      = Branch::all();
        $accountsTypes = SavingsProduct::where('auto_create', 1)->get();

        foreach ($rows as $row) {
            if ($row->filter()->isEmpty()) {
                continue;
            }

            // Check First name || Last name || Member no is empty
            if ($row[0] == '' || $row[1] == '' || $row[3] == '') {
                continue;
            }

            // Check Email is a valid email address
            if ($row[2] != '' && ! filter_var($row[2], FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // Check Email is unique across ALL branches
            if ($row[2] != '') {
                $member = Member::withoutGlobalScopes()->where('email', $row[2])->first();
                if ($member) {
                    continue;
                }
            }

            // Check Member no is unique across ALL branches
            $member = Member::withoutGlobalScopes()->where('member_no', $row[3])->first();
            if ($member) {
                continue;
            }

            // Check NIN is unique across ALL branches (column index 14, if provided)
            if (isset($row[14]) && $row[14] != '') {
                $member = Member::withoutGlobalScopes()->where('nin', strtoupper(trim($row[14])))->first();
                if ($member) {
                    continue;
                }
            }

            // Check Mobile is unique across ALL branches (column index 5)
            if (isset($row[5]) && $row[5] != '') {
                $member = Member::withoutGlobalScopes()->where('mobile', $row[5])->first();
                if ($member) {
                    continue;
                }
            }

            if ($row[13] != null) {
                $branch = $branches->where('name', $row[13])->first();

                if (! $branch) {
                    $branch = Branch::create([
                        'name' => $row[13],
                    ]);
                }
            }

            $member                = new Member();
            $member->first_name    = $row[0];
            $member->last_name     = $row[1];
            $member->email         = $row[2];
            $member->member_no     = $row[3];
            $member->country_code  = $row[4];
            $member->mobile        = $row[5];
            $member->business_name = $row[6];
            $member->gender        = strtolower($row[7]);
            $member->city          = $row[8];
            $member->state         = $row[9];
            $member->zip           = $row[10];
            $member->address       = $row[11];
            $member->credit_source = $row[12];
            $member->branch_id     = $row[13] != null ? $branch->id : null;
            $member->nin           = isset($row[14]) && $row[14] != '' ? strtoupper(trim($row[14])) : null;
            $member->status        = 1;

            $member->save();

            foreach ($accountsTypes as $accountType) {
                $savingsaccount                     = new SavingsAccount();
                $savingsaccount->account_number     = $accountType->account_number_prefix . $accountType->starting_account_number;
                $savingsaccount->member_id          = $member->id;
                $savingsaccount->savings_product_id = $accountType->id;
                $savingsaccount->status             = 1;
                $savingsaccount->opening_balance    = 0;
                $savingsaccount->description        = '';
                $savingsaccount->created_user_id    = auth()->id();

                $savingsaccount->save();

                //Increment account number
                $accountType->starting_account_number = $accountType->starting_account_number + 1;
                $accountType->save();
            }
        }

    }

    /**
     * @return int
     */
    public function startRow(): int
    {
        return 2;
    }

}