<?php

namespace App\Http\Middleware;

use App\Models\Member;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the "effective member" for every authenticated API request.
 *
 * Rules:
 *  - Staff user + X-Client-Id header  → use that client's member (validated)
 *  - Staff user + no header           → 403 (must select a client first)
 *  - Regular customer                 → always use their own member
 *
 * The resolved member is bound to the request as `$request->effectiveMember`
 * so all downstream controllers use it without re-querying.
 */
class StaffClientContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'code'    => 'UNAUTHENTICATED',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $isStaff = $user->user_type === 'staff';

        if ($isStaff) {
            $clientId = $request->header('X-Client-Id');

            if (!$clientId) {
                return response()->json([
                    'success' => false,
                    'code'    => 'CLIENT_REQUIRED',
                    'message' => 'Staff must select a client. Send X-Client-Id header.',
                ], 403);
            }

            $member = Member::where('id', $clientId)
                ->where('status', 1)
                ->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'code'    => 'CLIENT_NOT_FOUND',
                    'message' => 'Selected client not found or inactive.',
                ], 404);
            }

            $request->merge(['_effectiveMember' => $member]);
            $request->attributes->set('effectiveMember', $member);
        } else {
            // Regular customer — must have their own member record
            $member = $user->member;

            if (!$member || !$member->id) {
                return response()->json([
                    'success' => false,
                    'code'    => 'MEMBER_NOT_FOUND',
                    'message' => 'Member profile not found.',
                ], 404);
            }

            $request->attributes->set('effectiveMember', $member);
        }

        return $next($request);
    }
}
