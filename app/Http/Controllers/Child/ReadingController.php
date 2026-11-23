<?php

namespace App\Http\Controllers\Child;

use App\Http\Controllers\Controller;
use App\Models\Reading;
use App\Models\Story;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Opening a Story, the reading screen and a finished Reading's result.
 */
class ReadingController extends Controller
{
    /**
     * Open a Story: resume the active Child's unfinished Reading of it, or start one.
     */
    public function store(Request $request, Story $story): RedirectResponse
    {
        $child = $this->activeChild($request);

        $reading = $child->readings()->unfinished()->whereBelongsTo($story)->first()
            ?? $child->readings()->make(['started_at' => now()])->story()->associate($story);

        $reading->save();

        return to_route('readings.show', $reading);
    }

    /**
     * Show the reading screen at the Reading's next sentence, after any
     * pending retry or Syllable help of the sentence before it.
     */
    public function show(Request $request, Reading $reading): Response|RedirectResponse
    {
        Gate::authorize('view', [$reading, $this->activeChild($request)]);

        if ($reading->finished_at !== null) {
            return to_route('readings.result', $reading);
        }

        return Inertia::render('child/reading', [
            'reading' => ['id' => $reading->id],
            'story' => $reading->story->only(['id', 'title']),
            'progress' => $reading->progress(),
            'sentence' => $reading->nextSentence()?->toReaderArray(),
            'pendingWord' => $reading->pendingWordForReader(),
        ]);
    }

    /**
     * Show a finished Reading's result: its Stars, Accuracy, Reading speed and the words to practise.
     */
    public function result(Request $request, Reading $reading): Response|RedirectResponse
    {
        Gate::authorize('view', [$reading, $this->activeChild($request)]);

        if ($reading->finished_at === null) {
            return to_route('readings.show', $reading);
        }

        return Inertia::render('child/result', [
            'story' => $reading->story->only(['id', 'title']),
            'result' => [
                'stars' => $reading->stars,
                'accuracy' => (int) round(($reading->accuracy ?? 0) * 100),
                'readingSpeed' => (int) round($reading->reading_speed ?? 0),
                'wordsToPractise' => $reading->wordsToPractise(),
            ],
            'nextStory' => $reading->story->next()?->only(['id', 'title']),
        ]);
    }
}
