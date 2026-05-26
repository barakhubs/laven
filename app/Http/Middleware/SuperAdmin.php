<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Response;
use Auth;

class SuperAdmin
{
    public function handle($request, Closure $next)
    {
        if (!Auth::check() || !Auth::user()->isSuperAdmin()) {
            if (!$request->ajax()) {
                return back()->with('error', _lang('Permission denied !'));
            }
            return new Response('<h5 class="text-center text-danger">' . _lang('Permission denied !') . '</h5>');
        }

        return $next($request);
    }
}