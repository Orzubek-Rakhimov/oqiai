<?php

use App\Models\Reading;
use App\Models\Story;
use App\Models\Word;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

test('loading the story files creates each story with its sentences and words', function () {
    $this->artisan('stories:load', ['--path' => base_path('tests/Fixtures/stories')])
        ->expectsOutputToContain('ali-va-mushuk')
        ->assertSuccessful();

    expect(Story::count())->toBe(3);

    $story = Story::where('slug', 'ali-va-mushuk')->firstOrFail();
    expect($story->only(['title', 'level', 'minutes']))
        ->toBe(['title' => 'Ali va mushuk', 'level' => 1, 'minutes' => 2]);

    expect($story->sentences->pluck('text')->all())->toBe([
        'Ali uyga keldi.',
        "Mushuk o‘yinchoq bilan o'ynadi!",
        '— Salom, mushuk! — dedi Ali.',
    ]);

    expect($story->sentences[1]->words->map->only(['text', 'normalized', 'syllables'])->all())->toBe([
        ['text' => 'Mushuk', 'normalized' => 'mushuk', 'syllables' => ['mu', 'shuk']],
        ['text' => 'o‘yinchoq', 'normalized' => "o'yinchoq", 'syllables' => ["o'", 'yin', 'choq']],
        ['text' => 'bilan', 'normalized' => 'bilan', 'syllables' => ['bi', 'lan']],
        ['text' => "o'ynadi!", 'normalized' => "o'ynadi", 'syllables' => ["o'y", 'na', 'di']],
    ]);

    expect($story->sentences[2]->words->pluck('text')->all())
        ->toBe(['— Salom,', 'mushuk!', '— dedi', 'Ali.']);
});

/**
 * Write Story files into a fresh temporary folder and return its path.
 *
 * @param  array<string, string>  $files
 */
function storyFolder(array $files): string
{
    $folder = sys_get_temp_dir().'/oqi-stories-'.Str::random(8);
    File::ensureDirectoryExists($folder);

    foreach ($files as $slug => $contents) {
        File::put("{$folder}/{$slug}.txt", $contents);
    }

    return $folder;
}

/**
 * Get the stored syllables of every word of the Story, keyed by normalised word.
 *
 * @return array<string, list<string>>
 */
function storedSyllables(string $slug): array
{
    return Story::where('slug', $slug)->firstOrFail()
        ->words->pluck('syllables', 'normalized')->all();
}

test('words are split into syllables by the uzbek rule', function (string $word, array $syllables) {
    $folder = storyFolder(['soz' => "title: So'z\nlevel: 1\nminutes: 1\n---\n{$word}.\n"]);

    $this->artisan('stories:load', ['--path' => $folder])->assertSuccessful();

    expect(array_values(storedSyllables('soz')))->toBe([$syllables]);
})->with([
    'a consonant between vowels starts the next syllable' => ['bola', ['bo', 'la']],
    'the last consonant of a cluster starts the next syllable' => ['kutubxonaga', ['ku', 'tub', 'xo', 'na', 'ga']],
    "o' is a single vowel letter" => ["o'qituvchi", ["o'", 'qi', 'tuv', 'chi']],
    "g' is a single consonant letter" => ["tog'ga", ["tog'", 'ga']],
    'sh is a single letter' => ['shahar', ['sha', 'har']],
    'ch is a single letter' => ['bolacha', ['bo', 'la', 'cha']],
    'ng is a single letter' => ['singil', ['si', 'ngil']],
    'vowels next to each other are separate syllables' => ['oila', ['o', 'i', 'la']],
    'word-initial and word-final clusters stay with their vowel' => ["do'st", ["do'st"]],
    'a tutuq belgisi stays with its syllable' => ["ma'no", ["ma'", 'no']],
    'the split ignores capitals and apostrophe variants' => ['O‘RIK', ["o'", 'rik']],
]);

test('syllable overrides in the file replace the rule for those words', function () {
    $this->artisan('stories:load', ['--path' => base_path('tests/Fixtures/stories')])->assertSuccessful();

    expect(storedSyllables('dengiz-boyida'))
        ->toMatchArray([
            'dengiz' => ['den', 'giz'],
            'kutubxonaga' => ['ku', 'tub', 'xo', 'na', 'ga'],
        ]);
});

test('an override that does not spell its word fails that story file', function () {
    $folder = storyFolder(['soz' => "title: So'z\nlevel: 1\nminutes: 1\n---\nDengiz.\n---\ndengiz: den-gis\n"]);

    $this->artisan('stories:load', ['--path' => $folder])
        ->expectsOutputToContain('soz: the syllable override "den-gis" does not spell "dengiz"')
        ->assertFailed();

    expect(Story::count())->toBe(0);
});

test('running the command again changes nothing', function () {
    $path = base_path('tests/Fixtures/stories');
    $this->artisan('stories:load', ['--path' => $path])->assertSuccessful();
    $wordIds = Word::pluck('id')->all();

    $this->artisan('stories:load', ['--path' => $path])
        ->expectsOutputToContain('unchanged')
        ->assertSuccessful();

    expect(Story::count())->toBe(3)
        ->and(Word::pluck('id')->all())->toBe($wordIds);
});

test('a changed story file updates the story with the same slug', function () {
    $folder = storyFolder(['bog' => "title: Bog'\nlevel: 1\nminutes: 1\n---\nBog' katta.\n"]);
    $this->artisan('stories:load', ['--path' => $folder])->assertSuccessful();

    File::put("{$folder}/bog.txt", "title: Katta bog'\nlevel: 2\nminutes: 3\n---\nBog' juda katta. Unda olma bor.\n");
    $this->artisan('stories:load', ['--path' => $folder])
        ->expectsOutputToContain('updated')
        ->assertSuccessful();

    $story = Story::sole();
    expect($story->only(['slug', 'title', 'level', 'minutes']))
        ->toBe(['slug' => 'bog', 'title' => "Katta bog'", 'level' => 2, 'minutes' => 3])
        ->and($story->sentences->pluck('text')->all())->toBe(["Bog' juda katta.", 'Unda olma bor.'])
        ->and($story->words()->count())->toBe(6);
});

test('a story with readings is refused when its words would change and is left unchanged', function () {
    $folder = storyFolder([
        'bog' => "title: Bog'\nlevel: 1\nminutes: 1\n---\nBog' katta.\n",
        'uy' => "title: Uy\nlevel: 1\nminutes: 1\n---\nUy kichik.\n",
    ]);
    $this->artisan('stories:load', ['--path' => $folder])->assertSuccessful();
    Reading::factory()->for(Story::firstWhere('slug', 'bog'))->create();

    File::put("{$folder}/bog.txt", "title: Katta bog'\nlevel: 1\nminutes: 1\n---\nBog' juda katta.\n");
    File::put("{$folder}/uy.txt", "title: Uy\nlevel: 1\nminutes: 1\n---\nUy juda kichik.\n");

    $this->artisan('stories:load', ['--path' => $folder])
        ->expectsOutputToContain('bog: refused')
        ->assertFailed();

    $bog = Story::firstWhere('slug', 'bog');
    expect($bog->title)->toBe("Bog'")
        ->and($bog->words->pluck('text')->all())->toBe(["Bog'", 'katta.'])
        ->and(Story::firstWhere('slug', 'uy')->words->pluck('text')->all())->toBe(['Uy', 'juda', 'kichik.']);
});

test('a story with readings can still have its header and syllables changed', function () {
    $folder = storyFolder(['dengiz' => "title: Dengiz\nlevel: 1\nminutes: 1\n---\nDengiz katta.\n"]);
    $this->artisan('stories:load', ['--path' => $folder])->assertSuccessful();
    Reading::factory()->for(Story::sole())->create();
    $wordIds = Word::pluck('id')->all();

    File::put("{$folder}/dengiz.txt", "title: Ko'k dengiz\nlevel: 2\nminutes: 2\n---\nDengiz katta.\n---\ndengiz: den-giz\n");
    $this->artisan('stories:load', ['--path' => $folder])->assertSuccessful();

    expect(Story::sole()->only(['title', 'level', 'minutes']))->toBe(['title' => "Ko'k dengiz", 'level' => 2, 'minutes' => 2])
        ->and(storedSyllables('dengiz'))->toBe(['dengiz' => ['den', 'giz'], 'katta' => ['kat', 'ta']])
        ->and(Word::pluck('id')->all())->toBe($wordIds);
});

test('a story file that breaks the format is reported and the other files still load', function (string $contents, string $message) {
    $folder = storyFolder([
        'buzuq' => $contents,
        'uy' => "title: Uy\nlevel: 1\nminutes: 1\n---\nUy kichik.\n",
    ]);

    $this->artisan('stories:load', ['--path' => $folder])
        ->expectsOutputToContain("buzuq: {$message}")
        ->assertFailed();

    expect(Story::pluck('slug')->all())->toBe(['uy']);
})->with([
    'no text separator' => ["title: Uy\nlevel: 1\nminutes: 1\n", 'the file needs a header'],
    'missing title' => ["level: 1\nminutes: 1\n---\nUy.\n", 'the header "title" is missing'],
    'level out of range' => ["title: Uy\nlevel: 4\nminutes: 1\n---\nUy.\n", 'level must be 1, 2 or 3'],
    'empty text' => ["title: Uy\nlevel: 1\nminutes: 1\n---\n\n", 'the text is empty'],
]);
