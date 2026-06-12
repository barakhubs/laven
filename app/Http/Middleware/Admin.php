<?php

namespace App\Http\Middleware;

use Illuminate\Http\Response;
use Closure;
use Auth;

class Admin
{
    public function handle($request, Closure $next)
    {
        if (!Auth::User()->isAdmin()) {
            if (!$request->ajax()) {
                return back()->with('error', _lang('Permission denied !'));
            } else {
                return new Response('<h5 class="text-center text-danger">' . _lang('Permission denied !') . '</h5>');
            }
        }

        return $next($request);
    }
}