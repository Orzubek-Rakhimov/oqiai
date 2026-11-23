<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureActiveChild;
use App\Models\Child;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "Who is reading?" picker the Parent lands on after login.
 */
class PickerController extends Controller
{
    /**
     * Show the Parent's Children, or ask for the first one if there are none.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $children = $request->user()->children()->orderBy('id')->get(['id', 'name', 'avatar']);

        if ($children->isEmpty()) {
            return to_route('children.create');
        }

        return Inertia::render('picker', [
            'childProfiles' => $children,
        ]);
    }

    /**
     * Make the chosen Child the active Child for this session.
     */
    public function choose(Request $request, Child $child): RedirectResponse
    {
        Gate::authorize('choose', $child);

        $request->session()->put(EnsureActiveChild::SESSION_KEY, $child->id);

        return to_route('child.home');
    }
}
