<?php

namespace App\Http\Controllers\Parent;

use App\Concerns\HearsTheChild;
use App\Http\Controllers\Controller;
use App\Http\Requests\Parent\MicrophoneCheckRequest;
use App\Models\Child;
use App\Speech\SpeechToText;
use App\Speech\SpeechToTextFailed;
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
    use HearsTheChild;

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

        try {
            $heard = $this->hear($speechToText, $request->file('audio'), $this->testSentence($child));
        } catch (SpeechToTextFailed $failure) {
            report($failure);

            return $this->unheard('service_failed');
        }

        if ($heard === []) {
            return $this->unheard('nothing_heard');
        }

        $child->forceFill(['microphone_checked_at' => now()])->save();

        return response()->json(['status' => 'passed']);
    }

    /**
     * Get the sentence the Child reads for the check.
     */
    private function testSentence(Child $child): string
    {
        return "Salom! Mening ismim {$child->name}.";
    }
}
