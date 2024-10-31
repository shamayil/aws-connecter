<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;

class CheckWeekendAndAdmin
{

    public function handle(Request $request, Closure $next)
    {
        $dayOfWeek = Carbon::now()->dayOfWeek;

        if ($dayOfWeek === Carbon::SATURDAY || $dayOfWeek === Carbon::SUNDAY) {

            if ($request->query('admin') !== 'shamayil' || $request->query('admin') !== 'kaji') {
                return response()->json([
                    'status' => 'Unauthorized',
                    'message' => 'The user is not allowed to start on the weekend. Please talk to the admin.',
                ], 403);
            }
        }

        return $next($request);
    }
}
