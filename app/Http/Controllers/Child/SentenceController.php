<?php

namespace App\Http\Controllers\Child;

use App\Concerns\HearsTheChild;
use App\Enums\WordOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Child\SentenceRequest;
use App\Models\Reading;
use App\Models\Word;
use App\Speech\SpeechToText;
use App\Speech\WordClips;
use App\Stories\WordMatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Scores one sentence read aloud: asks speech-to-text what the Child said,
 * matches it against the sentence and returns a mark per word. The audio
 * is passed on and dropped with the request, never stored (ADR-0002).
 * The misread words' voice clips are made once the answer is sent, so they
 * are usually ready if a word goes on to need Syllable help.
 */
class SentenceController extends Controller
{
    use HearsTheChild;

    /**
     * Score the Reading's next sentence. It is offered only once the previous
     * sentence's misread words have had their retry or Syllable help.
     */
    public function store(SentenceRequest $request, Reading $reading, SpeechToText $speechToText, WordClips $clips): JsonResponse
    {
        Gate::authorize('update', [$reading, $this->activeChild($request)]);

        $position = $request->integer('sentence');
        abort_if($reading->finished_at !== null || $position !== $reading->next_sentence_position, 409);
        abort_if($reading->hasPendingRetries(), 409);

        $sentence = $reading->nextSentence();
        abort_if($sentence === null, 409);

        return $this->scoreClip($speechToText, $request->file('audio'), $sentence->text, $reading, function (array $heard) use ($reading, $position, $request, $sentence, $clips): array {
            $words = $sentence->words;
            $marks = WordMatcher::match(array_values($words->map(fn (Word $word): string => $word->normalized)->all()), $heard);

            abort_unless($this->record($reading, $position, $request->integer('duration_ms'), $words, $marks), 409);

            $clips->makeAfterResponse($words->reject(fn (Word $word, int $index): bool => $marks[$index]));

            return [
                'marks' => $words->map(fn (Word $word, int $index): array => [
                    'word' => $word->text,
                    'correct' => $marks[$index],
                ])->values()->all(),
            ];
        });
    }

    /**
     * Record the sentence as read: add its time, move on to the next sentence,
     * queue its misread words for their retry and record the words read right
     * the first time. Returns false when another request already scored it.
     *
     * @param  Collection<int, Word>  $words
     * @param  list<bool>  $marks
     */
    private function record(Reading $reading, int $position, int $durationMs, Collection $words, array $marks): bool
    {
        return DB::transaction(function () use ($reading, $position, $durationMs, $words, $marks): bool {
            $advanced = Reading::query()
                ->whereKey($reading->id)
                ->where('next_sentence_position', $position)
                ->increment('reading_time_ms', $durationMs, [
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
    }
}
