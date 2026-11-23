<?php

namespace App\Http\Controllers\Child;

use App\Concerns\HearsTheChild;
use App\Enums\WordOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Child\SentenceRequest;
use App\Models\Reading;
use App\Models\Word;
use App\Speech\SpeechToText;
use App\Speech\SpeechToTextFailed;
use App\Stories\WordMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Scores one sentence read aloud: asks speech-to-text what the Child said,
 * matches it against the sentence and returns a mark per word. The audio
 * is passed on and dropped with the request, never stored (ADR-0002).
 */
class SentenceController extends Controller
{
    use HearsTheChild;

    /**
     * Score the Reading's next sentence. It is offered only once the previous
     * sentence's misread words have had their retry or Syllable help.
     */
    public function store(SentenceRequest $request, Reading $reading, SpeechToText $speechToText): JsonResponse
    {
        Gate::authorize('update', [$reading, $this->activeChild($request)]);

        $position = $request->integer('sentence');
        abort_if($reading->finished_at !== null || $position !== $reading->next_sentence_position, 409);
        abort_if($reading->hasPendingRetries(), 409);

        $sentence = $reading->nextSentence();
        abort_if($sentence === null, 409);

        try {
            $heard = $this->hear($speechToText, $request->file('audio'), $sentence->text);
        } catch (SpeechToTextFailed $failure) {
            report($failure);

            return $this->unheard('service_failed');
        }

        if ($heard === []) {
            return $this->unheard('nothing_heard');
        }

        $words = $sentence->words;
        $marks = WordMatcher::match(array_values($words->map(fn (Word $word): string => $word->normalized)->all()), $heard);

        $scored = DB::transaction(function () use ($reading, $position, $request, $words, $marks): bool {
            $advanced = Reading::query()
                ->whereKey($reading->id)
                ->where('next_sentence_position', $position)
                ->increment('reading_time_ms', $request->integer('duration_ms'), [
                    'next_sentence_position' => $position + 1,
                    'pending_retry_word_ids' => json_encode(array_values(
                        $words->reject(fn (Word $word, int $index): bool => $marks[$index])->modelKeys(),
                    )),
                ]);

            if ($advanced === 0) {
                return false;
            }

            $reading->wordResults()->createMany(
                $words->filter(fn (Word $word, int $index): bool => $marks[$index])
                    ->map(fn (Word $word): array => [
                        'word_id' => $word->id,
                        'outcome' => WordOutcome::CorrectFirstTry,
                        'recorded_at' => now(),
                    ]),
            );

            return true;
        });

        abort_unless($scored, 409);

        $reading->refresh();
        $reading->finishIfComplete();

        return response()->json([
            'status' => 'scored',
            'marks' => $words->map(fn (Word $word, int $index): array => [
                'word' => $word->text,
                'correct' => $marks[$index],
            ])->values()->all(),
            'pendingWord' => $reading->pendingWordForReader(),
            'progress' => $reading->progress(),
            'nextSentence' => $reading->nextSentence()?->toReaderArray(),
        ]);
    }
}
