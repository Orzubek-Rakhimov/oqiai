<?php

namespace App\Stories;

use App\Models\Story;

/**
 * A parsed Story file: its header, its text split into sentences and words,
 * and each word's syllables (overrides first, then the Uzbek rule).
 * The format is described in resources/stories/README.md.
 */
final readonly class StoryFile
{
    private const array HEADER_KEYS = ['title', 'level', 'minutes'];

    /**
     * @param  list<array{text: string, words: list<array{text: string, normalized: string, syllables: list<string>}>}>  $sentences
     */
    public function __construct(
        public string $slug,
        public string $title,
        public int $level,
        public int $minutes,
        public array $sentences,
    ) {}

    /**
     * Read and parse the Story file at the given path. Its slug is the file name.
     *
     * @throws InvalidStoryFile
     */
    public static function read(string $path): self
    {
        $contents = (string) file_get_contents($path);
        $contents = str_replace(["\r\n", "\r"], "\n", ltrim($contents, "\u{FEFF}"));
        $parts = preg_split('/^\h*---\h*$/m', $contents) ?: [];

        if (count($parts) < 2 || count($parts) > 3) {
            throw new InvalidStoryFile('the file needs a header, a "---" line and the text, with an optional second "---" line before the syllable overrides');
        }

        $header = self::parseHeader($parts[0]);
        $overrides = self::parseOverrides($parts[2] ?? '');
        $sentences = self::parseText($parts[1], $overrides);

        if ($sentences === []) {
            throw new InvalidStoryFile('the text is empty');
        }

        return new self(
            slug: pathinfo($path, PATHINFO_FILENAME),
            title: $header['title'],
            level: $header['level'],
            minutes: $header['minutes'],
            sentences: $sentences,
        );
    }

    /**
     * @return array{title: string, level: int, minutes: int}
     */
    private static function parseHeader(string $header): array
    {
        $values = self::parseKeyValueLines($header, 'header');

        foreach (array_diff(array_keys($values), self::HEADER_KEYS) as $unknownKey) {
            throw new InvalidStoryFile("unknown header \"{$unknownKey}\"");
        }

        foreach (array_diff(self::HEADER_KEYS, array_keys($values)) as $missingKey) {
            throw new InvalidStoryFile("the header \"{$missingKey}\" is missing");
        }

        if (! in_array($values['level'], array_map(strval(...), Story::LEVELS), true)) {
            $levels = Story::LEVELS;
            $highestLevel = array_pop($levels);

            throw new InvalidStoryFile('level must be '.implode(', ', $levels).' or '.$highestLevel);
        }

        if (! ctype_digit($values['minutes']) || (int) $values['minutes'] < 1) {
            throw new InvalidStoryFile('minutes must be a whole number of at least 1');
        }

        return [
            'title' => $values['title'],
            'level' => (int) $values['level'],
            'minutes' => (int) $values['minutes'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function parseOverrides(string $section): array
    {
        $overrides = [];

        foreach (self::parseKeyValueLines($section, 'syllable override') as $word => $split) {
            $syllables = array_values(array_filter(array_map(
                fn (string $syllable): string => UzbekWord::normalize($syllable),
                explode('-', $split),
            )));
            $normalized = UzbekWord::normalize($word);

            if (implode('', $syllables) !== $normalized) {
                throw new InvalidStoryFile("the syllable override \"{$split}\" does not spell \"{$word}\"");
            }

            $overrides[$normalized] = $syllables;
        }

        return $overrides;
    }

    /**
     * @return array<string, string>
     */
    private static function parseKeyValueLines(string $section, string $kind): array
    {
        $values = [];

        foreach (explode("\n", $section) as $line) {
            if (trim($line) === '') {
                continue;
            }

            if (! str_contains($line, ':')) {
                throw new InvalidStoryFile("the {$kind} line \"".trim($line).'" needs a ":"');
            }

            [$key, $value] = array_map(trim(...), explode(':', $line, 2));
            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * Split paragraphs into sentences and sentences into words.
     *
     * @param  array<string, list<string>>  $overrides
     * @return list<array{text: string, words: list<array{text: string, normalized: string, syllables: list<string>}>}>
     */
    private static function parseText(string $text, array $overrides): array
    {
        $sentences = [];

        foreach (preg_split('/\n\h*\n/', trim($text)) ?: [] as $paragraph) {
            $paragraph = trim((string) preg_replace('/\s+/u', ' ', $paragraph));

            if ($paragraph === '') {
                continue;
            }

            $sentenceTexts = preg_split('/(?<=[.!?…]|[.!?…]["»”)])\s+(?=[—–"«“(-]*\s*\p{Lu})/u', $paragraph) ?: [];

            foreach ($sentenceTexts as $sentenceText) {
                $words = self::parseWords($sentenceText, $overrides);

                if ($words !== []) {
                    $sentences[] = ['text' => $sentenceText, 'words' => $words];
                }
            }
        }

        return $sentences;
    }

    /**
     * Split a sentence into words. A token with no letters, such as a
     * dialogue dash, is shown together with the word after it, or with the
     * word before it at the end of the sentence.
     *
     * @param  array<string, list<string>>  $overrides
     * @return list<array{text: string, normalized: string, syllables: list<string>}>
     */
    private static function parseWords(string $sentence, array $overrides): array
    {
        $words = [];
        $pendingPunctuation = '';

        foreach (explode(' ', $sentence) as $token) {
            $normalized = UzbekWord::normalize($token);

            if ($normalized === '') {
                $pendingPunctuation .= $token.' ';

                continue;
            }

            $words[] = [
                'text' => $pendingPunctuation.$token,
                'normalized' => $normalized,
                'syllables' => $overrides[$normalized] ?? UzbekWord::syllables($normalized),
            ];
            $pendingPunctuation = '';
        }

        if ($pendingPunctuation !== '' && $words !== []) {
            $lastWord = array_pop($words);
            $lastWord['text'] .= ' '.rtrim($pendingPunctuation);
            $words[] = $lastWord;
        }

        return $words;
    }
}
