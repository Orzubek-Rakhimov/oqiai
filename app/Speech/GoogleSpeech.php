<?php

namespace App\Speech;

/**
 * What the Google speech services share: the language codes they take and
 * the way they write a time.
 */
final class GoogleSpeech
{
    /**
     * The app's language codes and the regional codes Google takes for them.
     */
    private const array LANGUAGE_CODES = ['uz' => 'uz-UZ'];

    /**
     * Get Google's code for the language, such as "uz-UZ" for "uz".
     */
    public static function languageCode(string $language): string
    {
        return self::LANGUAGE_CODES[$language] ?? $language;
    }

    /**
     * Read a time Google writes as seconds with an "s", such as "0.100s".
     */
    public static function seconds(mixed $duration): ?float
    {
        if (! is_string($duration) || preg_match('/^(\d+(?:\.\d+)?)s$/', $duration, $matches) !== 1) {
            return null;
        }

        return (float) $matches[1];
    }
}
