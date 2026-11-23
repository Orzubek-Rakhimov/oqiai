<?php

namespace App\Speech;

use App\Models\Word;
use Illuminate\Support\Facades\Storage;

/**
 * Where a word's two voice clips live on the public disk. The whole-word
 * clip is named by the word's normalised form and the syllables clip by
 * its syllables, so the same word in any Story shares both. An apostrophe
 * is written as "_" to keep the names plain: o'yinchoq gives
 * audio/words/o_yinchoq.wav and audio/syllables/o_-yin-choq.wav.
 */
final class WordClips
{
    public const string DISK = 'public';

    /**
     * Get the paths of the word's clips on the public disk.
     *
     * @param  list<string>  $syllables
     * @return array{syllables: string, word: string}
     */
    public static function paths(string $normalized, array $syllables): array
    {
        return [
            'syllables' => 'audio/syllables/'.self::fileName(implode('-', $syllables)),
            'word' => 'audio/words/'.self::fileName($normalized),
        ];
    }

    /**
     * Get the URLs of the word's clips, or null for a clip not generated yet.
     *
     * @return array{syllables: string|null, word: string|null}
     */
    public static function urls(Word $word): array
    {
        $disk = Storage::disk(self::DISK);

        return array_map(
            fn (string $path): ?string => $disk->exists($path) ? $disk->url($path) : null,
            self::paths($word->normalized, $word->syllables),
        );
    }

    private static function fileName(string $name): string
    {
        return str_replace("'", '_', $name).'.wav';
    }
}
