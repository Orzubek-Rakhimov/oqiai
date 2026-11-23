<?php

use App\Http\Middleware\EnsureActiveChild;
use App\Models\Child;
use App\Speech\FakeSpeechToText;
use App\Speech\SpeechToText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Helpers shared by the feature tests.
|
*/

/**
 * Log in as the Child's Parent with the Child chosen on the picker.
 */
function actingAsChild(Child $child): mixed
{
    return test()->actingAs($child->parent)
        ->withSession([EnsureActiveChild::SESSION_KEY => $child->id]);
}

/**
 * Get the speech-to-text fake bound for every test, to script what the Child says.
 */
function speech(): FakeSpeechToText
{
    $fake = app(SpeechToText::class);

    assert($fake instanceof FakeSpeechToText);

    return $fake;
}
