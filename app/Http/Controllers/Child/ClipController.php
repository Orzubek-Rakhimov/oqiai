<?php

namespace App\Http\Controllers\Child;

use App\Http\Controllers\Controller;
use App\Models\Reading;
use App\Speech\WordClips;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The voice clip of the Reading's pending word, which Syllable help asks
 * for while it has none. A missing clip is made once the answer is sent,
 * so a later ask finds it and "Tinglash" appears without a reload.
 */
class ClipController extends Controller
{
    /**
     * Get the URL of the pending word's clip, or null while it is being made
     * or the voice service is failing.
     */
    public function show(Request $request, Reading $reading, WordClips $clips): JsonResponse
    {
        Gate::authorize('view', [$reading, $this->activeChild($request)]);

        $word = $reading->pendingWord();
        abort_if($reading->finished_at !== null || $word === null, 409);

        $clips->makeAfterResponse([$word]);

        return response()->json(['clip' => WordClips::url($word)]);
    }
}
