<?php

namespace App\Http\Controllers\Child;

use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The active Child's home screen: a greeting, their Streak and Stars total,
 * a suggested Story and a card to continue their unfinished Reading.
 */
class HomeController extends Controller
{
    /**
     * Show the active Child's home screen.
     */
    public function __invoke(Request $request): Response
    {
        /** @var Child $child */
        $child = $request->attributes->get(Child::class);

        $unfinishedReading = $child->readings()
            ->unfinished()
            ->with('story')
            ->latest('updated_at')
            ->latest('id')
            ->first();

        return Inertia::render('child/home', [
            'child' => $child->only(['id', 'name', 'grade', 'avatar']),
            'streak' => $child->streak(),
            'starsTotal' => $child->starsTotal(),
            'suggestedStory' => $child->suggestedStory()?->only(['id', 'title', 'level', 'minutes']),
            'continueReading' => $unfinishedReading === null ? null : [
                'id' => $unfinishedReading->id,
                'story' => $unfinishedReading->story->only(['id', 'title']),
                'progress' => $unfinishedReading->progress(),
            ],
        ]);
    }
}
