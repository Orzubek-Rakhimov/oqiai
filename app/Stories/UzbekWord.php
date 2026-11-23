<?php

namespace App\Stories;

/**
 * Uzbek Latin word rules shared by Story loading and Reading: the normalised
 * form that words are matched by, and the syllable split for Syllable help.
 */
final class UzbekWord
{
    /**
     * Every character typed for the o'/g' apostrophe or the tutuq belgisi.
     */
    private const array APOSTROPHES = ["'", '‘', '’', 'ʻ', 'ʼ', '`', '´', 'ʹ', '′'];

    /**
     * The vowels; o' is "o" followed by its apostrophe.
     */
    private const array VOWELS = ['a', 'e', 'i', 'o', 'u'];

    /**
     * Get the form a word is matched by: lower-case, without punctuation,
     * with every apostrophe variant written as a plain "'".
     */
    public static function normalize(string $word): string
    {
        $word = mb_strtolower(str_replace(self::APOSTROPHES, "'", $word));
        $word = (string) preg_replace("/[^\p{L}\p{N}']+/u", '', $word);
        $word = ltrim($word, "'");

        return (string) preg_replace("/(?<![og])'+$/u", '', $word);
    }

    /**
     * Get a word as the Story writes it, without the punctuation around it:
     * "keldi." gives "keldi" and "o‘yinchoq," gives "o‘yinchoq".
     */
    public static function asWritten(string $text): string
    {
        return (string) preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $text);
    }

    /**
     * Split a normalised word into syllables by the Uzbek rule: o', g', sh,
     * ch and ng are single letters; every syllable holds one vowel; a lone
     * consonant between vowels starts the next syllable, and of a cluster
     * only the last consonant does.
     *
     * @return list<string>
     */
    public static function syllables(string $normalized): array
    {
        $letters = self::letters($normalized);
        $vowelPositions = array_keys(array_filter(
            $letters,
            fn (string $letter): bool => in_array(rtrim($letter, "'"), self::VOWELS, true),
        ));

        if (count($vowelPositions) < 2) {
            return [$normalized];
        }

        $syllables = [];
        $start = 0;

        foreach (array_slice($vowelPositions, 1) as $index => $vowelPosition) {
            $previousVowel = $vowelPositions[$index];
            $boundary = $vowelPosition - $previousVowel > 1 ? $vowelPosition - 1 : $vowelPosition;
            $syllables[] = implode('', array_slice($letters, $start, $boundary - $start));
            $start = $boundary;
        }

        $syllables[] = implode('', array_slice($letters, $start));

        return $syllables;
    }

    /**
     * Split a normalised word into Uzbek letters. A tutuq belgisi stays on
     * the letter before it, so "ma'no" gives "m", "a'", "n", "o".
     *
     * @return list<string>
     */
    private static function letters(string $normalized): array
    {
        preg_match_all("/o'|g'|sh|ch|ng|\X'?/u", $normalized, $matches);

        return $matches[0];
    }
}
