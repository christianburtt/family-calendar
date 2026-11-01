<?php

use App\Support\JoinCode;

test('a generated code is three lowercase words joined by hyphens', function () {
    expect(JoinCode::generate())->toMatch('/^[a-z]+-[a-z]+-[a-z]+$/');
});

test('a generated code is built from the published word lists', function () {
    $code = JoinCode::generate();
    [$adjective, $firstNoun, $secondNoun] = explode('-', $code);

    expect(JoinCode::ADJECTIVES)->toContain($adjective);
    expect(JoinCode::NOUNS)->toContain($firstNoun);
    expect(JoinCode::NOUNS)->toContain($secondNoun);
});

test('codes are short enough to read aloud', function () {
    foreach (range(1, 50) as $attempt) {
        expect(strlen(JoinCode::generate()))->toBeLessThanOrEqual(30);
    }
});

test('the word lists are large, unique and free of hyphens', function () {
    expect(JoinCode::ADJECTIVES)->toHaveCount(258);
    expect(JoinCode::NOUNS)->toHaveCount(341);

    expect(array_unique(JoinCode::ADJECTIVES))->toHaveCount(count(JoinCode::ADJECTIVES));
    expect(array_unique(JoinCode::NOUNS))->toHaveCount(count(JoinCode::NOUNS));

    foreach ([...JoinCode::ADJECTIVES, ...JoinCode::NOUNS] as $word) {
        expect($word)->toMatch('/^[a-z]+$/');
    }
});

test('a code typed by a person is normalised into its canonical form', function (string $typed, string $expected) {
    expect(JoinCode::normalise($typed))->toBe($expected);
})->with([
    'already canonical' => ['brave-otter-teapot', 'brave-otter-teapot'],
    'spaces instead of hyphens' => ['Brave Otter Teapot', 'brave-otter-teapot'],
    'surrounding whitespace' => ['  brave-otter-teapot  ', 'brave-otter-teapot'],
    'underscores' => ['brave_otter_teapot', 'brave-otter-teapot'],
    'repeated separators' => ['BRAVE--OTTER__TEAPOT', 'brave-otter-teapot'],
    'mixed case and noise' => [' Brave  Otter, Teapot! ', 'brave-otter-teapot'],
    'nothing usable' => ['!!!', ''],
    'empty' => ['', ''],
    'a pasted share link' => [
        'https://family-calendar.test/join/brave-otter-teapot',
        'brave-otter-teapot',
    ],
    'a pasted share link with a trailing slash' => [
        'https://family-calendar.test/join/brave-otter-teapot/',
        'brave-otter-teapot',
    ],
    'a pasted share link with a query string' => [
        'https://family-calendar.test/join/brave-otter-teapot?utm=share',
        'brave-otter-teapot',
    ],
    'a bare path' => ['/join/brave-otter-teapot', 'brave-otter-teapot'],
]);
