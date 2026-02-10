<?php
namespace App\Http\Controllers;

use DataTables;
use App\Models\User;
use App\Models\Member;
use App\Mail\GeneralMail;
use App\Models\CustomField;
use App\Models\Transaction;
use App\Utilities\Overrider;
use App\Utilities\SmsHelper;
use Illuminate\Http\Request;
use App\Imports\MembersImport;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use App\Notifications\MemberRequestAccepted;

class MemberController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        return view('backend.member.list');
    }

    public function get_table_data() {
        $members = Member::select('members.*')
            ->with('branch')
            ->orderBy("members.id", "desc");

        return Datatables::eloquent($members)
            ->editColumn('branch.name', function ($member) {
                return $member->branch->name;
            })
            ->editColumn('photo', function ($member) {
                $photo = $member->photo != null ? profile_picture($member->photo) : asset('public/backend/images/avatar.png');
                return '<div class="profile_picture text-center">'
                    . '<img src="' . $photo . '" class="thumb-sm img-thumbnail">'
                    . '</div>';
            })
            ->addColumn('action', function ($member) {
                return '<div class="dropdown text-center">'
                . '<button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown">' . _lang('Action')
                . '&nbsp;</button>'
                . '<div class="dropdown-menu">'
                . '<a class="dropdown-item" href="' . route('members.edit', $member->id) . '"><i class="ti-pencil-alt"></i> ' . _lang('Edit') . '</a>'
                . '<a class="dropdown-item" href="' . route('members.show', $member->id) . '"><i class="ti-eye"></i>  ' . _lang('View') . '</a>'
                . '<a class="dropdown-item" href="' . route('member_documents.index', $member->id) . '"><i class="ti-files"></i>  ' . _lang('Documents') . '</a>'
                . '<form action="' . route('members.destroy', $member->id) . '" method="post">'
                . csrf_field()
                . '<input name="_method" type="hidden" value="DELETE">'
                . '<button class="dropdown-item btn-remove" type="submit"><i class="ti-trash"></i> ' . _lang('Delete') . '</button>'
                    . '</form>'
                    . '</div>'
                    . '</div>';
            })
            ->setRowId(function ($member) {
                return "row_" . $member->id;
            })
            ->rawColumns(['photo', 'action'])
            ->make(true);
    }

    public function pending_requests() {
        $data            = [];
        $data['members'] = Member::where('status', 0)
            ->withoutGlobalScopes(['status'])
            ->paginate(10);
        return view('backend.member.pending_requests', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        $customFields = CustomField::where('table', 'members')
            ->where('status', 1)
            ->orderBy("id", "asc")
            ->get();

        $memberNo = get_option('starting_member_no');
        return view('backend.member.create', compact('customFields', 'memberNo'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $validationRules = [
            'first_name'   => 'required',
            'last_name'    => 'required',
            'email'        => 'nullable|email|unique:members|max:191',
            'member_no'    => 'required|unique:members|max:50',
            'country_code' => 'required_with:mobile',
            'photo'        => 'nullable|image',
            //User Login Attributes
            'name'         => 'required_if:client_login,1|max:191',
            'login_email'  => 'required_if:client_login,1|email|unique:users,email|max:191',
            'password'     => 'required_if:client_login,1|max:20|min:6',
            'status'       => 'required_if:client_login,1',
        ];

        $validationMessages = [
            'name.required_if'           => 'Name is required',
            'login_email.required_if'    => 'Email is required',
            'password.required_if'       => 'Password is required',
            'country_code.required_with' => 'Country code is required',
        ];

        // Custom field validation
        $customFields = CustomField::where('table', 'members')
            ->orderBy("id", "desc")
            ->get();
        $customValidation = generate_custom_field_validation($customFields);

        $validationRules    = array_merge($validationRules, $customValidation['rules']);
        $validationMessages = array_merge($validationMessages, $customValidation['messages']);

        $validator = Validator::make($request->all(), $validationRules, $validationMessages);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('members.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $photo = 'default.png';
        if ($request->hasfile('photo')) {
            $file  = $request->file('photo');
            $photo = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/profile/", $photo);
        }

        DB::beginTransaction();

        // Store custom field data
        $customFieldsData = store_custom_field_data($customFields);

        //Create Login details
        if ($request->client_login == 1) {
            $user                  = new User();
            $user->name            = $request->input('name');
            $user->email           = $request->input('login_email');
            $user->password        = Hash::make($request->password);
            $user->user_type       = 'customer';
            $user->status          = $request->input('status');
            $user->profile_picture = $photo;
            $user->save();
        }

        $member             = new Member();
        $member->first_name = $request->input('first_name');
        $member->last_name  = $request->input('last_name');
        if (auth()->user()->user_type == 'admin') {
            $member->branch_id = $request->branch_id;
        } else {
            $member->branch_id = auth()->user()->branch_id;
        }
        if ($request->client_login == 1) {
            $member->user_id = $user->id;
        }
        $member->email         = $request->input('email');
        $member->country_code  = $request->input('country_code');
        $member->mobile        = $request->input('mobile');
        $member->business_name = $request->input('business_name');
        $member->member_no     = get_option('starting_member_no', $request->input('member_no'));
        $member->gender        = $request->input('gender');
        $member->city          = $request->input('city');
        $member->state         = $request->input('state');
        $member->zip           = $request->input('zip');
        $member->address       = $request->input('address');
        $member->credit_source = $request->input('credit_source');
        $member->photo         = $photo;
        $member->custom_fields = json_encode($customFieldsData);

        $member->save();

        //Increment Member No
        $memberNo = get_option('starting_member_no');
        if ($memberNo != '') {
            update_option('starting_member_no', $memberNo + 1);
        }

        $this->generateAccounts($member->id);
        
        DB::commit();

        if (! $request->ajax()) {
            return redirect()->route('members.show', $member->id)->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $member, 'table' => '#members_table']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id) {
        $member       = Member::withoutGlobalScopes(['status'])->find($id);
        $customFields = CustomField::where('table', 'members')
            ->where('status', 1)
            ->orderBy("id", "asc")
            ->get();
        if (! $request->ajax()) {
            return view('backend.member.view', compact('member', 'id', 'customFields'));
        } else {
            return view('backend.member.modal.view', compact('member', 'id', 'customFields'));
        }
    }

    public function get_member_transaction_data($member_id) {
        $transactions = Transaction::select('transactions.*')
            ->with(['member', 'account', 'account.savings_type'])
            ->where('member_id', $member_id)
            ->orderBy("transactions.trans_date", "desc");

        return Datatables::eloquent($transactions)
            ->editColumn('member.first_name', function ($transactions) {
                return $transactions->member->first_name . ' ' . $transactions->member->last_name;
            })
            ->editColumn('dr_cr', function ($transactions) {
                return strtoupper($transactions->dr_cr);
            })
            ->editColumn('status', function ($transactions) {
                return transaction_status($transactions->status);
            })
            ->editColumn('amount', function ($transaction) {
                $symbol = $transaction->dr_cr == 'dr' ? '-' : '+';
                $class  = $transaction->dr_cr == 'dr' ? 'text-danger' : 'text-success';
                return '<span class="' . $class . '">' . $symbol . ' ' . decimalPlace($transaction->amount, currency($transaction->account->savings_type->currency->name)) . '</span>';
            })
            ->editColumn('type', function ($transaction) {
                return str_replace('_', ' ', $transaction->type);
            })
            ->filterColumn('member.first_name', function ($query, $keyword) {
                $query->whereHas('member', function ($query) use ($keyword) {
                    return $query->where("first_name", "like", "{$keyword}%")
                        ->orWhere("last_name", "like", "{$keyword}%");
                });
            }, true)
            ->addColumn('action', function ($transaction) {
                return '<div class="dropdown text-center">'
                . '<button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown">' . _lang('Action')
                . '&nbsp;</button>'
                . '<div class="dropdown-menu">'
                . '<a class="dropdown-item" href="' . route('transactions.edit', $transaction['id']) . '"><i class="ti-pencil-alt"></i> ' . _lang('Edit') . '</a>'
                . '<a class="dropdown-item" href="' . route('transactions.show', $transaction['id']) . '"><i class="ti-eye"></i>  ' . _lang('View') . '</a>'
                . '<form action="' . route('transactions.destroy', $transaction['id']) . '" method="post">'
                . csrf_field()
                . '<input name="_method" type="hidden" value="DELETE">'
                . '<button class="dropdown-item btn-remove" type="submit"><i class="ti-trash"></i> ' . _lang('Delete') . '</button>'
                    . '</form>'
                    . '</div>'
                    . '</div>';
            })
            ->setRowId(function ($transaction) {
                return "row_" . $transaction->id;
            })
            ->rawColumns(['action', 'status', 'amount'])
            ->make(true);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $customFields = CustomField::where('table', 'members')
            ->where('status', 1)
            ->orderBy("id", "asc")
            ->get();
        $member = Member::withoutGlobalScopes(['status'])->find($id);
        if (! $request->ajax()) {
            return view('backend.member.edit', compact('member', 'id', 'customFields'));
        } else {
            return view('backend.member.modal.edit', compact('member', 'id', 'customFields'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        $member = Member::withoutGlobalScopes(['status'])->find($id);

        $validationRules = [
            'first_name'   => 'required',
            'last_name'    => 'required',
            //'branch_id'    => 'required',
            'email'        => [
                'nullable',
                'email',
                Rule::unique('members')->ignore($id),
            ],
            'member_no'    => [
                'required',
                Rule::unique('members')->ignore($id),
            ],
            'country_code' => 'required_with:mobile',
            'photo'        => 'nullable|image',
            'name'         => 'required_if:client_login,1|max:191', // User Login Attribute
            'login_email'  => [
                'required_if:client_login,1',
                Rule::unique('users', 'email')->ignore($member->user_id),
            ],                                              // User Login Attribute
            'password'     => 'nullable|max:20|min:6',      // User Login Attribute
            'status'       => 'required_if:client_login,1', // User Login Attribute
        ];

        $validationMessages = [
            'name.required_if'           => 'Name is required',
            'login_email.required_if'    => 'Email is required',
            'password.required_if'       => 'Password is required',
            'country_code.required_with' => 'Country code is required',
        ];

        // Custom field validation
        $customFields = CustomField::where('table', 'members')
            ->orderBy("id", "desc")
            ->get();
        $customValidation = generate_custom_field_validation($customFields, true);

        $validationRules    = array_merge($validationRules, $customValidation['rules']);
        $validationMessages = array_merge($validationMessages, $customValidation['messages']);

        $validator = Validator::make($request->all(), $validationRules, $validationMessages);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('members.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        if ($request->hasfile('photo')) {
            $file  = $request->file('photo');
            $photo = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/profile/", $photo);
        }

        DB::beginTransaction();

        // Store custom field data
        $customFieldsData = store_custom_field_data($customFields, json_decode($member->custom_fields, true));

        if ($request->client_login == 1) {
            if ($member->user_id != NULL) {
                $user = User::find($member->user_id);
            } else {
                $user = new User();
            }
            $user->name   = $request->input('name');
            $user->email  = $request->input('login_email');
            $user->status = $request->input('status');
            if ($request->password) {
                $user->password = Hash::make($request->password);
            }
            $user->user_type = 'customer';
            $user->save();
        }

        $member->first_name = $request->input('first_name');
        $member->last_name  = $request->input('last_name');
        if (auth()->user()->user_type == 'admin') {
            $member->branch_id = $request->branch_id;
        } else {
            $member->branch_id = auth()->user()->branch_id;
        }
        if ($request->client_login == 1) {
            $member->user_id = $user->id;
        }
        $member->email         = $request->input('email');
        $member->country_code  = $request->input('country_code');
        $member->mobile        = $request->input('mobile');
        $member->business_name = $request->input('business_name');
        $member->member_no     = $request->input('member_no');
        $member->gender        = $request->input('gender');
        $member->city          = $request->input('city');
        $member->state         = $request->input('state');
        $member->zip           = $request->input('zip');
        $member->address       = $request->input('address');
        $member->credit_source = $request->input('credit_source');
        if ($request->hasfile('photo')) {
            $member->photo = $photo;
        }
        $member->custom_fields = json_encode($customFieldsData);

        $member->save();

        DB::commit();

        if (! $request->ajax()) {
            return redirect()->route('members.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $member, 'table' => '#members_table']);
        }
    }

    public function send_email(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        Overrider::load("Settings");

        $validator = Validator::make($request->all(), [
            'user_email' => 'required',
            'subject'    => 'required',
            'message'    => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)
                    ->withInput();
            }
        }

        //Send email
        $subject = $request->input("subject");
        $message = $request->input("message");

        $mail          = new \stdClass();
        $mail->subject = $subject;
        $mail->body    = $message;

        try {
            Mail::to($request->user_email)->send(new GeneralMail($mail));
        } catch (\Exception $e) {
            if (! $request->ajax()) {
                return back()->with('error', _lang('Sorry, Error Occured !'));
            } else {
                return response()->json(['result' => 'error', 'message' => _lang('Sorry, Error Occured !')]);
            }
        }

        if (! $request->ajax()) {
            return back()->with('success', _lang('Email Send Sucessfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Email Send Sucessfully'), 'data' => $contact]);
        }
    }

    public function send_sms(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $validator = Validator::make($request->all(), [
            'phone'   => 'required',
            'message' => 'required:max:160',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)
                    ->withInput();
            }
        }

        //Send message
        $message = $request->input("message");

        if (get_option('sms_gateway') == 'none') {
            return back()->with('error', _lang('Sorry, SMS Gateway is disabled !'));
        }

        try {
            $sms = new SmsHelper();
            $sms->send($request->phone, $message);
        } catch (\Exception $e) {
            if (! $request->ajax()) {
                return back()->with('error', _lang('Sorry, Error Occured !'));
            } else {
                return response()->json(['result' => 'error', 'message' => _lang('Sorry, Error Occured !')]);
            }
        }

        if (! $request->ajax()) {
            return back()->with('success', _lang('SMS Send Sucessfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('SMS Send Sucessfully'), 'data' => $contact]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $member = Member::find($id);
        if ($member->user) {
            $member->user->delete();
        }
        $member->delete();
        return redirect()->route('members.index')->with('success', _lang('Deleted Successfully'));
    }

    public function accept_request(Request $request, $id) {
        if ($request->isMethod('get')) {
            $member = Member::withoutGlobalScopes(['status'])->find($id);
            return view('backend.member.modal.accept_request', compact('member'));
        } else {
            $validator = Validator::make($request->all(), [
                'member_no' => [
                    'required',
                    Rule::unique('members')->ignore($id),
                ],
            ]);

            if ($validator->fails()) {
                if ($request->ajax()) {
                    return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
                } else {
                    return back()->withErrors($validator)->withInput();
                }
            }

            DB::beginTransaction();

            $member            = Member::withoutGlobalScopes(['status'])->find($id);
            $member->member_no = $request->member_no;
            $member->status    = 1;
            $member->save();

            $member->user->status = 1;
            $member->user->save();

            $this->generateAccounts($member->id);

            DB::commit();

            if ($member->status == 1) {
                try {
                    $member->notify(new MemberRequestAccepted($member));
                } catch (\Exception $e) {}
            }

            if (! $request->ajax()) {
                return redirect()->route('members.index')->with('success', _lang('Member Request Accepted'));
            } else {
                return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Member Request Accepted'), 'data' => $member, 'table' => '#members_table']);
            }

        }
    }

    public function reject_request($id) {
        $member = Member::withoutGlobalScopes(['status'])->find($id);
        $member->user->delete();
        $member->delete();
        return redirect()->back()->with('error', _lang('Member Request Rejected'));
    }

    public function import(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.member.import');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $validator = Validator::make($request->all(), [
                'file' => 'required|mimes:xlsx',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            $new_rows = 0;

            DB::beginTransaction();

            $previous_rows = Member::count();

            $data   = [];
            $import = Excel::import(new MembersImport($data), $request->file('file'));

            $current_rows = Member::count();

            $new_rows = $current_rows - $previous_rows;

            DB::commit();

            if ($new_rows == 0) {
                return back()->with('error', _lang('Nothing Imported, Data may already exists !'));
            }
            return back()->with('success', $new_rows . ' ' . _lang('Rows Imported Sucessfully'));
        }
    }

    private function generateAccounts($member_id) {
        $accountsTypes = SavingsProduct::where('auto_create', 1)->get();
        foreach ($accountsTypes as $accountType) {
            $savingsaccount                     = new SavingsAccount();
            $savingsaccount->account_number     = $accountType->account_number_prefix . $accountType->starting_account_number;
            $savingsaccount->member_id          = $member_id;
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