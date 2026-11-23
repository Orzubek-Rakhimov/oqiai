<?php

namespace App\Http\Controllers\Child;

use App\Concerns\HearsTheChild;
use App\Enums\WordOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Child\RetryRequest;
use App\Models\Reading;
use App\Speech\SpeechToText;
use App\Speech\SpeechToTextFailed;
use App\Stories\WordMatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Scores the one retry a misread word gets, read on its own. Correct is
 * recorded as "correct on retry"; misread again opens Syllable help and
 * the word is recorded as "needed Syllable help". The audio is passed on
 * and dropped with the request, never stored (ADR-0002).
 */
class RetryController extends Controller
{
    use HearsTheChild;

    /**
     * Score the retry of the Reading's pending word.
     */
    public function store(RetryRequest $request, Reading $reading, SpeechToText $speechToText): JsonResponse
    {
        Gate::authorize('update', [$reading, $this->activeChild($request)]);
        abort_if($reading->finished_at !== null || $reading->syllable_help_word_id !== null, 409);

        $word = $reading->pendingWord();
        abort_if(
            $word === null
            || $word->sentence->position !== $request->integer('sentence')
            || $word->position !== $request->integer('word'),
            409,
        );

        try {
            $heard = $this->hear($speechToText, $request->file('audio'), $word->text);
        } catch (SpeechToTextFailed $failure) {
            report($failure);

            return $this->unheard('service_failed');
        }

        if ($heard === []) {
            return $this->unheard('nothing_heard');
        }

        $correct = WordMatcher::match([$word->normalized], $heard)[0];

        try {
            DB::transaction(function () use ($reading, $word, $correct, $request): void {
                $reading->wordResults()->create([
                    'word_id' => $word->id,
                    'outcome' => $correct ? WordOutcome::CorrectOnRetry : WordOutcome::NeededSyllableHelp,
                    'recorded_at' => now(),
                ]);

                Reading::query()->whereKey($reading->id)->increment('reading_time_ms', $request->integer('duration_ms'), [
                    'pending_retry_word_ids' => json_encode(array_slice($reading->pending_retry_word_ids ?? [], 1)),
                    'syllable_help_word_id' => $correct ? null : $word->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            abort(409);
        }

        $reading->refresh();
        $reading->finishIfComplete();

        return response()->json([
            'status' => 'scored',
            'correct' => $correct,
            'pendingWord' => $reading->pendingWordForReader(),
            'progress' => $reading->progress(),
            'nextSentence' => $reading->nextSentence()?->toReaderArray(),
        ]);
    }
}
