<?php

namespace App\Http\Controllers\Child;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\Story;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Story library: every Story, open to every Child whatever their Grade.
 */
class StoryController extends Controller
{
    /**
     * Show the library, optionally filtered to one Level.
     */
    public function index(Request $request): Response
    {
        /** @var Child $child */
        $child = $request->attributes->get(Child::class);
        $level = in_array($request->integer('level'), [1, 2, 3], true) ? $request->integer('level') : null;

        $bestStars = $child->bestStarsPerStory();

        $stories = Story::query()
            ->when($level, fn ($query) => $query->where('level', $level))
            ->orderBy('level')
            ->orderBy('title')
            ->get()
            ->map(fn (Story $story): array => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
                'level' => $story->level,
                'minutes' => $story->minutes,
                'isNew' => ! $bestStars->has($story->id),
                'bestStars' => $bestStars->get($story->id),
            ]);

        return Inertia::render('child/stories', [
            'level' => $level,
            'stories' => $stories,
            'starsTotal' => $bestStars->sum(),
        ]);
    }
}
