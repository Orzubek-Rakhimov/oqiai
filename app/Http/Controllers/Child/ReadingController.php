<?php

namespace App\Http\Controllers\Child;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\Reading;
use App\Models\Story;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Opening a Story and the reading screen.
 */
class ReadingController extends Controller
{
    /**
     * Open a Story: resume the active Child's unfinished Reading of it, or start one.
     */
    public function store(Request $request, Story $story): RedirectResponse
    {
        /** @var Child $child */
        $child = $request->attributes->get(Child::class);

        $reading = $child->readings()->unfinished()->whereBelongsTo($story)->first()
            ?? $child->readings()->make(['started_at' => now()])->story()->associate($story);

        $reading->save();

        return to_route('readings.show', $reading);
    }

    /**
     * Show the reading screen at the Reading's next sentence.
     */
    public function show(Request $request, Reading $reading): Response
    {
        /** @var Child $child */
        $child = $request->attributes->get(Child::class);
        abort_unless($reading->child()->is($child), 404);

        return Inertia::render('child/reading', [
            'reading' => ['id' => $reading->id],
            'story' => $reading->story->only(['id', 'title']),
            'progress' => $reading->progress(),
            'sentence' => $reading->nextSentence()?->toReaderArray(),
        ]);
    }
}
