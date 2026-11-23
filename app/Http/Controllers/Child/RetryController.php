<?php

namespace App\Http\Controllers\Child;

use App\Concerns\HearsTheChild;
use App\Enums\WordOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Child\RetryRequest;
use App\Models\Reading;
use App\Models\Word;
use App\Speech\SpeechToText;
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

        return $this->scoreClip($speechToText, $request->file('audio'), $word->text, $reading, function (array $heard) use ($reading, $word, $request): array {
            $correct = WordMatcher::match([$word->normalized], $heard)[0];

            $this->record($reading, $word, $correct, $request->integer('duration_ms'));

            return ['correct' => $correct];
        });
    }

    /**
     * Record the retry: the word's outcome, the clip's time, and either the
     * next word in the retry queue or Syllable help for this one.
     */
    private function record(Reading $reading, Word $word, bool $correct, int $durationMs): void
    {
        try {
            DB::transaction(function () use ($reading, $word, $correct, $durationMs): void {
                $reading->wordResults()->create([
                    'word_id' => $word->id,
                    'outcome' => $correct ? WordOutcome::CorrectOnRetry : WordOutcome::NeededSyllableHelp,
                    'recorded_at' => now(),
                ]);

                Reading::query()->whereKey($reading->id)->increment('reading_time_ms', $durationMs, [
                    'pending_retry_word_ids' => json_encode(array_slice($reading->pending_retry_word_ids ?? [], 1)),
                    'syllable_help_word_id' => $correct ? null : $word->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            abort(409);
        }
    }
}
