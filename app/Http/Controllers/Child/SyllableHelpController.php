<?php

namespace App\Http\Controllers\Child;

use App\Http\Controllers\Controller;
use App\Http\Requests\Child\SyllableHelpRequest;
use App\Models\Reading;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Ends Syllable help once the Child has read the word once more. That read
 * is not scored and its time is not reading time; the word stays recorded
 * as "needed Syllable help", and the next misread word or sentence follows.
 */
class SyllableHelpController extends Controller
{
    /**
     * Close Syllable help for the Reading's pending word.
     */
    public function store(SyllableHelpRequest $request, Reading $reading): JsonResponse
    {
        Gate::authorize('update', [$reading, $this->activeChild($request)]);

        $word = $reading->syllableHelpWord;
        abort_if(
            $reading->finished_at !== null
            || $word === null
            || $word->sentence->position !== $request->integer('sentence')
            || $word->position !== $request->integer('word'),
            409,
        );

        $closed = Reading::query()
            ->whereKey($reading->id)
            ->where('syllable_help_word_id', $word->id)
            ->update(['syllable_help_word_id' => null]);

        abort_if($closed === 0, 409);

        $reading->refresh();
        $reading->finishIfComplete();

        return response()->json([
            'status' => 'done',
            'pendingWord' => $reading->pendingWordForReader(),
            'progress' => $reading->progress(),
            'nextSentence' => $reading->nextSentence()?->toReaderArray(),
        ]);
    }
}
