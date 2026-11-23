<?php

use App\Models\Child;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('creating the first child leads to the microphone check', function () {
    $parent = User::factory()->create();

    $response = $this->actingAs($parent)->post(route('children.store'), [
        'name' => 'Ali',
        'grade' => 2,
        'avatar' => 'fox',
    ]);

    $child = $parent->children()->sole();
    $response->assertRedirect(route('children.microphone', $child, absolute: false));

    $this->get(route('children.microphone', $child))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('parent/children/microphone')
            ->where('child.id', $child->id)
            ->where('child.name', 'Ali')
            ->where('testSentence', 'Salom! Mening ismim Ali.')
            ->where('checked', false),
        );
});

/**
 * Build a test sentence recording upload.
 *
 * @return array{audio: UploadedFile}
 */
function microphoneCheckRecording(): array
{
    return ['audio' => UploadedFile::fake()->createWithContent('check.webm', 'fake-opus-bytes')];
}

test('the check passes when the tutor hears the child read the test sentence', function () {
    $this->freezeTime();
    $child = Child::factory()->create(['name' => 'Ali']);
    speech()->hears('Salom mening ismim Ali');

    $this->actingAs($child->parent)
        ->post(route('children.microphone.store', $child), microphoneCheckRecording())
        ->assertOk()
        ->assertExactJson(['status' => 'passed']);

    expect(speech()->requests())->toHaveCount(1)
        ->and(speech()->requests()[0])->toMatchArray([
            'audio' => 'fake-opus-bytes',
            'expectedText' => 'Salom! Mening ismim Ali.',
            'language' => 'uz',
        ])
        ->and($child->fresh()->microphone_checked_at?->toIso8601String())->toBe(now()->toIso8601String());

    $this->get(route('children.microphone', $child))
        ->assertInertia(fn (Assert $page) => $page->where('checked', true));
});

test('the check is not passed when nothing was heard', function (Closure $script) {
    $child = Child::factory()->create(['name' => 'Ali']);
    $script();

    $this->actingAs($child->parent)
        ->post(route('children.microphone.store', $child), microphoneCheckRecording())
        ->assertOk()
        ->assertExactJson(['status' => 'unheard', 'reason' => 'nothing_heard']);

    expect($child->fresh()->microphone_checked_at)->toBeNull();
})->with([
    'silence' => [fn () => speech()->hearsNothing()],
    'only punctuation' => [fn () => speech()->hears('... ?')],
]);

test('the check is not passed when speech-to-text fails', function () {
    $child = Child::factory()->create();
    speech()->fails();

    $this->actingAs($child->parent)
        ->post(route('children.microphone.store', $child), microphoneCheckRecording())
        ->assertOk()
        ->assertExactJson(['status' => 'unheard', 'reason' => 'service_failed']);

    expect($child->fresh()->microphone_checked_at)->toBeNull();
});

test('a parent can skip the check and do it later from the child profile', function () {
    $parent = User::factory()->create();
    $this->actingAs($parent)->post(route('children.store'), [
        'name' => 'Ali',
        'grade' => 2,
        'avatar' => 'fox',
    ]);
    $child = $parent->children()->sole();

    $this->get(route('picker'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('picker')
            ->where('childProfiles.0.id', $child->id),
        );

    $this->get(route('children.edit', $child))
        ->assertInertia(fn (Assert $page) => $page->where('microphoneChecked', false));

    expect($child->fresh()->microphone_checked_at)->toBeNull();
});

test('the child profile shows when the microphone check has passed', function () {
    $child = Child::factory()->create();
    $this->actingAs($child->parent)->post(route('children.microphone.store', $child), microphoneCheckRecording());

    $this->get(route('children.edit', $child))
        ->assertInertia(fn (Assert $page) => $page->where('microphoneChecked', true));
});

test('a parent cannot check the microphone for another parent\'s child', function () {
    $child = Child::factory()->create();
    $otherParent = User::factory()->create();

    $this->actingAs($otherParent)->get(route('children.microphone', $child))->assertNotFound();
    $this->actingAs($otherParent)
        ->post(route('children.microphone.store', $child), microphoneCheckRecording())
        ->assertNotFound();

    expect(speech()->requests())->toBe([])
        ->and($child->fresh()->microphone_checked_at)->toBeNull();
});

test('a guest is sent to log in', function () {
    $child = Child::factory()->create();

    $this->get(route('children.microphone', $child))->assertRedirect(route('login', absolute: false));
    $this->post(route('children.microphone.store', $child), microphoneCheckRecording())
        ->assertRedirect(route('login', absolute: false));
});

test('the test recording is never stored', function () {
    Storage::fake('local');
    Storage::fake('public');
    $child = Child::factory()->create();

    $this->actingAs($child->parent)
        ->post(route('children.microphone.store', $child), microphoneCheckRecording())
        ->assertExactJson(['status' => 'passed']);

    expect(Storage::disk('local')->allFiles())->toBe([])
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('the check needs a recording', function () {
    $child = Child::factory()->create();

    $this->actingAs($child->parent)
        ->postJson(route('children.microphone.store', $child), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['audio']);

    expect(speech()->requests())->toBe([]);
});
