<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Reports\ChildProgress;
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
            'progress' => $selectedChild === null ? null : $this->progressOf($selectedChild),
        ]);
    }

    /**
     * Get the progress figures the Parent screen shows for one Child.
     *
     * @return array{hasRead: bool, daysPractised: int, storiesFinished: int, readingSpeedByWeek: list<array{weekStart: string, readingSpeed: int|null}>, hardWords: list<array{word: string, count: int}>, streak: int, starsTotal: int}
     */
    private function progressOf(Child $child): array
    {
        $progress = new ChildProgress($child);

        return [
            'hasRead' => $child->readings()->exists(),
            'daysPractised' => $progress->daysPractisedThisWeek(),
            'storiesFinished' => $progress->storiesFinishedThisWeek(),
            'readingSpeedByWeek' => $progress->readingSpeedByWeek(),
            'hardWords' => $progress->hardWords(),
            'streak' => $child->streak(),
            'starsTotal' => $child->starsTotal(),
        ];
    }
}
