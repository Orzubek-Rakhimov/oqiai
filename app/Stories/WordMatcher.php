<?php

namespace App\Stories;

/**
 * Lines up the words the Child said against the words of the text, in
 * order. Skipped and extra words are tolerated: the expected words that
 * match form the longest run of words heard in the same order. Words are
 * compared by their normalised form.
 */
final class WordMatcher
{
    /**
     * Mark each expected word as said (true) or misread (false).
     *
     * @param  list<string>  $expected  The normalised words of the text.
     * @param  list<string>  $heard  The normalised words that were said.
     * @return list<bool> One mark per expected word.
     */
    public static function match(array $expected, array $heard): array
    {
        $expectedCount = count($expected);
        $heardCount = count($heard);

        // $longest[$i][$j] is the longest common run of $expected from $i and $heard from $j.
        $longest = array_fill(0, $expectedCount + 1, array_fill(0, $heardCount + 1, 0));

        for ($i = $expectedCount - 1; $i >= 0; $i--) {
            for ($j = $heardCount - 1; $j >= 0; $j--) {
                $longest[$i][$j] = $expected[$i] === $heard[$j]
                    ? $longest[$i + 1][$j + 1] + 1
                    : max($longest[$i + 1][$j], $longest[$i][$j + 1]);
            }
        }

        $marks = array_fill(0, $expectedCount, false);

        for ($i = 0, $j = 0; $i < $expectedCount && $j < $heardCount;) {
            if ($expected[$i] === $heard[$j]) {
                $marks[$i] = true;
                $i++;
                $j++;
            } elseif ($longest[$i + 1][$j] >= $longest[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return array_values($marks);
    }
}
