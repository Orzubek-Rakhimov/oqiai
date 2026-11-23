<?php

namespace App\Http\Controllers\Parent;

use App\Enums\Avatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Parent\ChildRequest;
use App\Models\Child;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Parent adds, edits and deletes their Child profiles.
 */
class ChildController extends Controller
{
    /**
     * Show the form for a new Child, worded for the first one after sign-up.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('parent/children/create', [
            'isFirstChild' => $request->user()->children()->doesntExist(),
            'avatars' => Avatar::keys(),
        ]);
    }

    /**
     * Store a new Child for the Parent. The first one goes on to the microphone check.
     */
    public function store(ChildRequest $request): RedirectResponse
    {
        $isFirstChild = $request->user()->children()->doesntExist();

        $child = $request->user()->children()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Child added.')]);

        return $isFirstChild ? to_route('children.microphone', $child) : to_route('parent.home');
    }

    /**
     * Show the form for editing one of the Parent's Children.
     */
    public function edit(Child $child): Response
    {
        Gate::authorize('update', $child);

        return Inertia::render('parent/children/edit', [
            'child' => $child->only(['id', 'name', 'grade', 'avatar']),
            'avatars' => Avatar::keys(),
            'microphoneChecked' => $child->microphone_checked_at !== null,
        ]);
    }

    /**
     * Update one of the Parent's Children.
     */
    public function update(ChildRequest $request, Child $child): RedirectResponse
    {
        Gate::authorize('update', $child);

        $child->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Child updated.')]);

        return to_route('parent.home');
    }

    /**
     * Delete one of the Parent's Children.
     */
    public function destroy(Child $child): RedirectResponse
    {
        Gate::authorize('delete', $child);

        $child->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Child deleted.')]);

        return to_route('parent.home');
    }
}
