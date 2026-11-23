<?php

namespace App\Http\Middleware;

use App\Models\Child;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Child screens need an active Child, chosen on the picker, that belongs to
 * the logged-in Parent. Without one the Parent is sent back to the picker.
 */
class EnsureActiveChild
{
    public const string SESSION_KEY = 'active_child_id';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $activeChild = $request->user()?->children()
            ->whereKey($request->session()->get(self::SESSION_KEY))
            ->first();

        if ($activeChild === null) {
            $request->session()->forget(self::SESSION_KEY);

            return to_route('picker');
        }

        $request->attributes->set(Child::class, $activeChild);

        return $next($request);
    }
}
