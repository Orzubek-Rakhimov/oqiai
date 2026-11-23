<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Parent area, opened from the picker without a PIN.
 */
class HomeController extends Controller
{
    /**
     * Show the Parent's Children with ways to add, edit and delete them, and
     * the progress of one Child (the first unless another is asked for).
     */
    public function __invoke(Request $request): Response
    {
        $children = $request->user()->children()->orderBy('id')->get(['id', 'user_id', 'name', 'grade', 'avatar']);

        $selectedChild = $request->filled('child')
            ? $children->firstWhere('id', $request->integer('child')) ?? abort(404)
            : $children->first();

        return Inertia::render('parent/home', [
            'childProfiles' => $children->map(fn (Child $child): array => $child->only(['id', 'name', 'grade', 'avatar'])),
            'selectedChildId' => $selectedChild?->id,
            'progress' => $selectedChild === null ? null : [
                'hasRead' => $selectedChild->readings()->exists(),
                'daysPractised' => $selectedChild->daysPractisedThisWeek(),
                'storiesFinished' => $selectedChild->storiesFinishedThisWeek(),
                'readingSpeedByWeek' => $selectedChild->readingSpeedByWeek(),
                'hardWords' => $selectedChild->hardWords(),
                'streak' => $selectedChild->streak(),
                'starsTotal' => $selectedChild->starsTotal(),
            ],
        ]);
    }
}
