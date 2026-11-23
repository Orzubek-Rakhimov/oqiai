<?php

namespace Tests;

use App\Speech\FakeSpeechToText;
use App\Speech\FakeTextToSpeech;
use App\Speech\SpeechToText;
use App\Speech\TextToSpeech;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Bind the speech-to-text and text-to-speech fakes for every test, so no test reaches a real service.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(SpeechToText::class, new FakeSpeechToText);
        $this->app->instance(TextToSpeech::class, new FakeTextToSpeech);
    }
}
