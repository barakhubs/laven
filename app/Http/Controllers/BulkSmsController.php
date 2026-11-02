<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Models\SmsContact;
use App\Models\User;
use App\Utilities\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Bulk SMS: one message to every active member (or only those with an active
 * loan), staff user and/or the extra numbers saved on the page. A number that appears in more than one group
 * gets the message once. {name} in the message becomes each person's first
 * name, so "Hello Dear {name}" reaches JACKSON MUKASA as "Hello Dear Jackson".
 */
class BulkSmsController extends Controller {

    public function index() {
        $groups   = $this->groups();
        $contacts = SmsContact::orderBy('name')->orderBy('phone')->get();

        // For each mix of groups (keyed like "contacts,members"), how many
        // people have a first name of each length, so the page can work out
        // exactly how many SMS a message with {name} costs. "u7" is a 7-letter
        // name with special characters; "-" is no name.
        $names = [];
        foreach (self::mixes(array_keys($groups)) as $mix) {
            $lengths = [];
            foreach ($this->recipients($groups, $mix) as $name) {
                $key = $name === null ? '-' : (preg_match('/[^\x00-\x7F]/', $name) ? 'u' : '') . mb_strlen($name);
                $lengths[$key] = ($lengths[$key] ?? 0) + 1;
            }
            $names[implode(',', $mix)] = $lengths;
        }

        return view('backend.bulk_sms.index', [
            'counts'     => array_map('count', $groups),
            'names'      => $names,
            'sampleName' => collect($groups['members'])->filter()->first() ?? 'Jackson',
            'contacts'   => $contacts,
            'unitCost'   => (float) get_option('sms_unit_cost', 0),
        ]);
    }

    /** What the gateway charges for one SMS, used for the cost estimate. */
    public function save_cost(Request $request) {
        $request->validate(['sms_unit_cost' => 'required|numeric|min:0']);

        update_option('sms_unit_cost', $request->sms_unit_cost);

        return redirect()->route('bulk_sms.index')->with('success', _lang('Cost per SMS saved'));
    }

    public function send(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $request->validate([
            'message'       => 'required|string|max:459',
            'recipients'    => 'required|array|min:1',
            'recipients.*'  => 'in:members,loan_clients,staff,contacts',
            'fallback_name' => 'nullable|string|max:30',
        ], [
            'recipients.required' => _lang('Choose who should receive the message'),
        ]);

        if (get_option('sms_gateway', 'none') == 'none') {
            return back()->withInput()->with('error', _lang('Sorry, SMS Gateway is disabled !'));
        }

        $recipients = $this->recipients($this->groups(), $request->recipients);

        if (empty($recipients)) {
            return back()->withInput()->with('error', _lang('There are no phone numbers to send to'));
        }

        // Numbers grouped by the exact text they get, so people with the same
        // first name (or everyone, when there's no {name}) go in one batch.
        $fallback = trim((string) $request->fallback_name);
        $messages = [];
        foreach ($recipients as $number => $name) {
            $text = trim(str_ireplace('{name}', $name ?? $fallback, $request->message));
            $messages[$text][] = $number;
        }

        $smsCount = 0;
        foreach ($messages as $text => $numbers) {
            $smsCount += count($numbers) * self::smsParts($text);
        }
        $cost = $smsCount * (float) get_option('sms_unit_cost', 0);

        Log::channel('daily')->info('Bulk SMS: sending', [
            'user_id'    => Auth::id(),
            'recipients' => $request->recipients,
            'count'      => count($recipients),
            'sms'        => $smsCount,
            'cost'       => $cost,
        ]);

        try {
            $sms = app(SmsHelper::class);
            foreach ($messages as $text => $numbers) {
                $sms->sendMany($numbers, $text);
            }
        } catch (\Exception $e) {
            Log::channel('daily')->error('Bulk SMS: failed', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', _lang('Sorry, Error Occured !'));
        }

        $sent = _lang('Message sent to') . ' ' . count($recipients) . ' ' . _lang('phone numbers') . ' (' . $smsCount . ' SMS';
        if ($cost > 0) {
            $sent .= ', ' . _lang('about') . ' ' . decimalPlace($cost, currency());
        }

        return redirect()->route('bulk_sms.index')->with('success', $sent . ')');
    }

    /**
     * How many SMS one person is charged for. Plain text fits 160 characters
     * in one SMS and 153 per part after that; a message with emoji or other
     * special characters fits 70, then 67 per part. Same rule as the page.
     */
    public static function smsParts(string $message): int {
        $unicode = preg_match('/[^\x00-\x7F]/', $message);
        $length  = $unicode ? mb_strlen($message) : strlen($message);
        [$single, $part] = $unicode ? [70, 67] : [160, 153];

        return $length <= $single ? 1 : (int) ceil($length / $part);
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
     * Every non-empty mix of the groups, each in alphabetical order like the
     * page sorts the ticked boxes: ['contacts'], ['contacts', 'members'], ...
     */
    private static function mixes(array $groups): array {
        sort($groups);
        $mixes = [[]];
        foreach ($groups as $group) {
            foreach ($mixes as $mix) {
                $mixes[] = array_merge($mix, [$group]);
            }
        }

        return array_slice($mixes, 1);
    }

    /**
     * Each group's phone numbers in +256... form, mapped to the person's
     * first name (null when there's no name). loan_clients are the active
     * members who have an active loan, in every branch.
     *
     * @return array{members: array<string, ?string>, loan_clients: array<string, ?string>, staff: array<string, ?string>, contacts: array<string, ?string>}
     */
    private function groups(): array {
        $members = Member::whereNotNull('mobile')->where('mobile', '!=', '')
            ->get(['id', 'first_name', 'business_name', 'mobile', 'country_code'])
            ->map(fn ($member) => [SmsContact::normalize(format_recipient_number($member)), self::firstName($member->first_name ?: $member->business_name), $member->id]);

        $borrowers   = Loan::withoutGlobalScopes()->where('status', 1)->distinct()->pluck('borrower_id')->flip();
        $loanClients = $members->filter(fn ($row) => $borrowers->has($row[2]));

        $staff = User::where('user_type', '!=', 'customer')->where('status', 1)
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->get(['name', 'phone'])
            ->map(fn ($user) => [SmsContact::normalize($user->phone), self::firstName($user->name)]);

        $contacts = SmsContact::get(['name', 'phone'])
            ->map(fn ($contact) => [$contact->phone, self::firstName($contact->name)]);

        // The first name found for a number is kept.
        $clean = fn ($rows) => $rows->filter(fn ($row) => $row[0] !== null)
            ->reduce(fn ($numbers, $row) => $numbers + [$row[0] => $row[1]], []);

        return [
            'members'      => $clean($members),
            'loan_clients' => $clean($loanClients),
            'staff'        => $clean($staff),
            'contacts'     => $clean($contacts),
        ];
    }

    /**
     * Everyone in the chosen groups, each number once. A member's name is
     * used over a staff user's, and either over an extra number's.
     *
     * @return array<string, ?string>
     */
    private function recipients(array $groups, array $chosen): array {
        $recipients = [];
        foreach (['members', 'loan_clients', 'staff', 'contacts'] as $group) {
            if (! in_array($group, $chosen)) {
                continue;
            }
            foreach ($groups[$group] as $number => $name) {
                if (! array_key_exists($number, $recipients) || $recipients[$number] === null) {
                    $recipients[$number] = $name;
                }
            }
        }

        return $recipients;
    }

    /** "JACKSON PETER" -> "Jackson"; names are often typed in capitals. */
    public static function firstName(?string $name): ?string {
        $first = preg_split('/\s+/', trim((string) $name))[0];

        return $first === '' ? null : mb_convert_case(mb_strtolower($first), MB_CASE_TITLE);
    }
}
