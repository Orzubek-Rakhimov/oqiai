<?php

namespace App\Http\Controllers\Child;

use App\Enums\WordOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Child\SentenceRequest;
use App\Models\Child;
use App\Models\Reading;
use App\Models\Word;
use App\Speech\RecognisedWord;
use App\Speech\SpeechToText;
use App\Speech\SpeechToTextFailed;
use App\Stories\UzbekWord;
use App\Stories\WordMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Scores one sentence read aloud: asks speech-to-text what the Child said,
 * matches it against the sentence and returns a mark per word. The audio
 * is passed on and dropped with the request, never stored (ADR-0002).
 */
class SentenceController extends Controller
{
    /**
     * Score the Reading's next sentence.
     */
    public function store(SentenceRequest $request, Reading $reading, SpeechToText $speechToText): JsonResponse
    {
        /** @var Child $child */
        $child = $request->attributes->get(Child::class);
        abort_unless($reading->child()->is($child), 404);

        $position = $request->integer('sentence');
        abort_if($reading->finished_at !== null || $position !== $reading->next_sentence_position, 409);

        $sentence = $reading->nextSentence();
        abort_if($sentence === null, 409);

        $audio = $request->file('audio');

        try {
            $recognised = $speechToText->transcribe((string) $audio->get(), $audio->getClientMimeType(), $sentence->text, 'uz');
        } catch (SpeechToTextFailed $failure) {
            report($failure);

            return $this->unheard('service_failed');
        }

        $heard = $this->normalizedWords($recognised);

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
            'progress' => $reading->progress(),
            'nextSentence' => $reading->nextSentence()?->toReaderArray(),
        ]);
    }

    /**
     * Get the normalised words that were said, leaving out anything that is not a word.
     *
     * @param  list<RecognisedWord>  $recognised
     * @return list<string>
     */
    private function normalizedWords(array $recognised): array
    {
        $words = [];

        foreach ($recognised as $word) {
            foreach (preg_split('/\s+/u', $word->text, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                $normalized = UzbekWord::normalize($part);

                if ($normalized !== '') {
                    $words[] = $normalized;
                }
            }
        }

        return $words;
    }

    /**
     * Tell the Child to read the sentence again. Nothing is recorded.
     *
     * @param  'nothing_heard'|'service_failed'  $reason
     */
    private function unheard(string $reason): JsonResponse
    {
        return response()->json(['status' => 'unheard', 'reason' => $reason]);
    }
}
