<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parent\MicrophoneCheckRequest;
use App\Models\Child;
use App\Speech\RecognisedWord;
use App\Speech\SpeechToText;
use App\Speech\SpeechToTextFailed;
use App\Stories\UzbekWord;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checks during setup that the tutor can hear the Child: the Child reads
 * "Salom! Mening ismim <name>." and any recognised word counts as a pass.
 * Only when it passed is kept; the audio is dropped with the request (ADR-0002).
 */
class MicrophoneCheckController extends Controller
{
    /**
     * Show the microphone check for one of the Parent's Children.
     */
    public function show(Child $child): Response
    {
        Gate::authorize('update', $child);

        return Inertia::render('parent/children/microphone', [
            'child' => $child->only(['id', 'name', 'avatar']),
            'testSentence' => $this->testSentence($child),
            'checked' => $child->microphone_checked_at !== null,
        ]);
    }

    /**
     * Check the Child's recording of the test sentence through speech-to-text.
     */
    public function store(MicrophoneCheckRequest $request, Child $child, SpeechToText $speechToText): JsonResponse
    {
        Gate::authorize('update', $child);

        $audio = $request->file('audio');

        try {
            $recognised = $speechToText->transcribe((string) $audio->get(), $audio->getClientMimeType(), $this->testSentence($child), 'uz');
        } catch (SpeechToTextFailed $failure) {
            report($failure);

            return $this->unheard('service_failed');
        }

        if (! $this->heardAnyWord($recognised)) {
            return $this->unheard('nothing_heard');
        }

        $child->forceFill(['microphone_checked_at' => now()])->save();

        return response()->json(['status' => 'passed']);
    }

    /**
     * Determine whether speech-to-text recognised at least one word.
     *
     * @param  list<RecognisedWord>  $recognised
     */
    private function heardAnyWord(array $recognised): bool
    {
        foreach ($recognised as $word) {
            if (UzbekWord::normalize($word->text) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Tell the Parent the tutor did not hear the Child. Nothing is stored.
     *
     * @param  'nothing_heard'|'service_failed'  $reason
     */
    private function unheard(string $reason): JsonResponse
    {
        return response()->json(['status' => 'unheard', 'reason' => $reason]);
    }

    /**
     * Get the sentence the Child reads for the check.
     */
    private function testSentence(Child $child): string
    {
        return "Salom! Mening ismim {$child->name}.";
    }
}
