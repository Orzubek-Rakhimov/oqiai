<?php

namespace App\Concerns;

use App\Http\Presenters\ReadingScreen;
use App\Models\Reading;
use App\Speech\RecognisedWord;
use App\Speech\SpeechToText;
use App\Speech\SpeechToTextFailed;
use App\Stories\UzbekWord;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Hearing a clip the Child read aloud: what speech-to-text recognised, as
 * normalised words to match, the reply once it is scored and the reply when
 * nothing could be scored. The clip is only passed on, never stored (ADR-0002).
 */
trait HearsTheChild
{
    /**
     * Hear a clip read during the Reading and score it. When nothing was heard
     * or speech-to-text failed, nothing is recorded and the Child reads again.
     * Otherwise the given scoring records what was heard and returns its own
     * reply fields; the Reading then finishes if complete, and the reply adds
     * where the reading screen goes next.
     *
     * @param  Closure(list<string>): array<string, mixed>  $score  Gets the normalised words heard.
     */
    protected function scoreClip(SpeechToText $speechToText, UploadedFile $audio, string $expectedText, Reading $reading, Closure $score): JsonResponse
    {
        try {
            $heard = $this->hear($speechToText, $audio, $expectedText);
        } catch (SpeechToTextFailed $failure) {
            report($failure);

            return $this->unheard('service_failed');
        }

        if ($heard === []) {
            return $this->unheard('nothing_heard');
        }

        $scored = $score($heard);

        $reading->refresh();
        $reading->finishIfComplete();

        return response()->json(['status' => 'scored', ...$scored, ...ReadingScreen::afterStep($reading)]);
    }

    /**
     * Get the normalised words the Child said in the clip, leaving out anything
     * that is not a word. Speech-to-text is told the clip's format as detected
     * from its content, never the type the browser labelled it with.
     *
     * @return list<string>
     *
     * @throws SpeechToTextFailed
     */
    protected function hear(SpeechToText $speechToText, UploadedFile $audio, string $expectedText): array
    {
        $recognised = $speechToText->transcribe((string) $audio->get(), (string) $audio->getMimeType(), $expectedText, 'uz');

        return $this->normalizedWords($recognised);
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
     * Tell the Child to read again. Nothing is recorded.
     *
     * @param  'nothing_heard'|'service_failed'  $reason
     */
    protected function unheard(string $reason): JsonResponse
    {
        return response()->json(['status' => 'unheard', 'reason' => $reason]);
    }
}
