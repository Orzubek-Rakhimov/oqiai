<?php

namespace Tests;

use App\Speech\FakeSpeechToText;
use App\Speech\SpeechToText;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Bind the speech-to-text fake for every test, so no test reaches a real service.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(SpeechToText::class, new FakeSpeechToText);
    }
}
