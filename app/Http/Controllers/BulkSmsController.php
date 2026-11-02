<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\SmsContact;
use App\Models\User;
use App\Utilities\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Bulk SMS: one message to every active member, staff user and/or the extra
 * numbers saved on the page. A number that appears in more than one group
 * gets the message once.
 */
class BulkSmsController extends Controller {

    public function index() {
        $groups   = $this->groups();
        $contacts = SmsContact::orderBy('name')->orderBy('phone')->get();

        // How many different numbers each mix of groups reaches, keyed like
        // "contacts,members", so the page can say exactly how many will get it.
        $totals = [];
        foreach ([['members'], ['staff'], ['contacts'], ['members', 'staff'], ['contacts', 'members'], ['contacts', 'staff'], ['contacts', 'members', 'staff']] as $mix) {
            $totals[implode(',', $mix)] = count(array_unique(array_merge(...array_map(fn ($group) => $groups[$group], $mix))));
        }

        return view('backend.bulk_sms.index', [
            'counts'   => array_map('count', $groups),
            'totals'   => $totals,
            'contacts' => $contacts,
        ]);
    }

    public function send(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $request->validate([
            'message'      => 'required|string|max:459',
            'recipients'   => 'required|array|min:1',
            'recipients.*' => 'in:members,staff,contacts',
        ], [
            'recipients.required' => _lang('Choose who should receive the message'),
        ]);

        if (get_option('sms_gateway', 'none') == 'none') {
            return back()->withInput()->with('error', _lang('Sorry, SMS Gateway is disabled !'));
        }

        $groups  = array_intersect_key($this->groups(), array_flip($request->recipients));
        $numbers = array_values(array_unique(array_merge(...array_values($groups))));

        if (empty($numbers)) {
            return back()->withInput()->with('error', _lang('There are no phone numbers to send to'));
        }

        Log::channel('daily')->info('Bulk SMS: sending', [
            'user_id'    => Auth::id(),
            'recipients' => $request->recipients,
            'count'      => count($numbers),
        ]);

        try {
            app(SmsHelper::class)->sendMany($numbers, $request->message);
        } catch (\Exception $e) {
            Log::channel('daily')->error('Bulk SMS: failed', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', _lang('Sorry, Error Occured !'));
        }

        return redirect()->route('bulk_sms.index')
            ->with('success', _lang('Message sent to') . ' ' . count($numbers) . ' ' . _lang('phone numbers'));
    }

    /**
     * Save one or more extra numbers. Several can be pasted at once,
     * separated by commas or new lines; they all get the same name.
     */
    public function store_contacts(Request $request) {
        $request->validate([
            'name'   => 'nullable|string|max:100',
            'phones' => 'required|string',
        ]);

        $added   = 0;
        $invalid = [];
        foreach (preg_split('/[,;\r\n]+/', $request->phones) as $typed) {
            if (trim($typed) === '') {
                continue;
            }
            $phone = SmsContact::normalize($typed);
            if ($phone === null) {
                $invalid[] = trim($typed);
                continue;
            }
            $contact = SmsContact::firstOrCreate(['phone' => $phone], [
                'name'            => $request->name,
                'created_user_id' => Auth::id(),
            ]);
            $added += $contact->wasRecentlyCreated ? 1 : 0;
        }

        $message = $added . ' ' . _lang('phone numbers added');
        if (! empty($invalid)) {
            // Leave only the bad numbers in the box so they can be corrected.
            return redirect()->route('bulk_sms.index')->withInput(['name' => $request->name, 'phones' => implode("\n", $invalid)])
                ->with('error', $message . '. ' . _lang('Not valid phone numbers') . ': ' . implode(', ', $invalid));
        }

        return redirect()->route('bulk_sms.index')->with('success', $message);
    }

    public function destroy_contact($id) {
        SmsContact::findOrFail($id)->delete();

        return redirect()->route('bulk_sms.index')->with('success', _lang('Phone number removed'));
    }

    /**
     * Phone numbers in each group, in +256... form, without repeats.
     *
     * @return array{members: string[], staff: string[], contacts: string[]}
     */
    private function groups(): array {
        $members = Member::whereNotNull('mobile')->where('mobile', '!=', '')
            ->get(['mobile', 'country_code'])
            ->map(fn ($member) => SmsContact::normalize(format_recipient_number($member)));

        $staff = User::where('user_type', '!=', 'customer')->where('status', 1)
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->pluck('phone')
            ->map(fn ($phone) => SmsContact::normalize($phone));

        $contacts = SmsContact::pluck('phone');

        $clean = fn ($numbers) => $numbers->filter()->unique()->values()->all();

        return [
            'members'  => $clean($members),
            'staff'    => $clean($staff),
            'contacts' => $clean($contacts),
        ];
    }
}
