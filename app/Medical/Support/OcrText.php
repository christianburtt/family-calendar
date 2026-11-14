<?php

namespace App\Medical\Support;

use Illuminate\Support\Str;

/**
 * Text folding for the medical search columns.
 *
 * Romanian medical paperwork is full of diacritics (ș, ț, ă, î, â) that people
 * rarely type when searching. Every searchable column stores a folded copy next
 * to the original so `reteta` finds `Rețetă` on both MySQL and SQLite. MySQL's
 * unicode collation folds diacritics on its own, SQLite's does not; folding on
 * write keeps the two engines behaving identically.
 */
final class OcrText
{
    /**
     * Fold text to lowercase ASCII for matching.
     *
     * Transliterator is preferred because it handles the Romanian cedilla forms
     * (ş, ţ) as well as the comma-below ones. It folds one character to one
     * character for Romanian, so offsets stay aligned with the original, but
     * not for every script: "ß" becomes "ss" and ligatures expand. Callers that
     * slice results out of the folded string must clamp against the original.
     */
    public static function fold(?string $text): string
    {
        $text = (string) $text;

        if ($text === '') {
            return '';
        }

        $transliterator = class_exists(\Transliterator::class)
            ? \Transliterator::create('Any-Latin; Latin-ASCII')
            : null;

        $folded = $transliterator?->transliterate($text);

        $lowered = mb_strtolower(is_string($folded) ? $folded : Str::ascii($text));

        return Str::squish($lowered);
    }

    /**
     * Fold a list of words into a single search string.
     *
     * @param  iterable<string|null>  $parts
     */
    public static function foldAll(iterable $parts): string
    {
        $folded = [];

        foreach ($parts as $part) {
            $value = self::fold($part);

            if ($value !== '') {
                $folded[] = $value;
            }
        }

        return implode(' ', $folded);
    }
}
