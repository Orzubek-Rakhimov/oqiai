<?php

use App\Models\Child;
use App\Models\Reading;
use App\Models\Story;

beforeEach(function () {
    $this->artisan('stories:load', ['--path' => base_path('tests/Fixtures/stories')])->assertSuccessful();
    $this->child = Child::factory()->create();
    $this->reading = Reading::factory()->for($this->child)->for(Story::firstWhere('slug', 'ali-va-mushuk'))->create();
});

test('a parent account can send 30 clips a minute to speech-to-text, across sentences, retries and the microphone check', function () {
    foreach (range(1, 10) as $attempt) {
        actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), ['sentence' => 0])->assertUnprocessable();
        actingAsChild($this->child)->postJson(route('readings.retries.store', $this->reading), [])->assertUnprocessable();
        $this->actingAs($this->child->parent)->postJson(route('children.microphone.store', $this->child), [])->assertUnprocessable();
    }

    actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), sentenceClip(0))->assertTooManyRequests();
    actingAsChild($this->child)->postJson(route('readings.retries.store', $this->reading), wordClip(0, 0))->assertTooManyRequests();
    $this->actingAs($this->child->parent)->postJson(route('children.microphone.store', $this->child), [])->assertTooManyRequests();

    $this->travel(1)->minute();

    actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), sentenceClip(0))->assertOk();
});

test('one parent reaching the limit does not hold up another', function () {
    $otherChild = Child::factory()->create();
    $otherReading = Reading::factory()->for($otherChild)->for($this->reading->story)->create();

    foreach (range(1, 30) as $attempt) {
        actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), ['sentence' => 0]);
    }

    actingAsChild($this->child)->postJson(route('readings.sentences.store', $this->reading), sentenceClip(0))->assertTooManyRequests();
    actingAsChild($otherChild)->postJson(route('readings.sentences.store', $otherReading), sentenceClip(0))->assertOk();
});
