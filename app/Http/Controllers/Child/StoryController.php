<?php

namespace App\Http\Controllers\Child;

use App\Http\Controllers\Controller;
use App\Models\Story;
use Illuminate\Database\Eloquent\Builder;
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
        $child = $this->activeChild($request);
        $level = in_array($request->integer('level'), Story::LEVELS, true) ? $request->integer('level') : null;

        $bestStars = $child->bestStarsPerStory();

        $stories = Story::query()
            ->when($level, fn (Builder $query, int $level) => $query->where('level', $level))
            ->inLibraryOrder()
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
            'levels' => Story::LEVELS,
            'level' => $level,
            'stories' => $stories,
            'starsTotal' => $bestStars->sum(),
        ]);
    }
}
