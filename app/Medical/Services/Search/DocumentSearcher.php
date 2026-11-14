<?php

namespace App\Medical\Services\Search;

use App\Medical\Enums\DocumentStatus;
use App\Medical\Models\MedicalDocument;
use App\Medical\Support\OcrText;
use App\Models\Calendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Matches what a household types against everything a document holds.
 *
 * Three folded columns are searched: the document's own words (title, tag,
 * category label, patient name), the folder's patient name, and the extracted
 * text of every page. Page text is the reason this exists at all: "colesterol"
 * should find the lab result without anyone remembering which folder or when.
 */
final class DocumentSearcher
{
    /**
     * How much text to keep either side of a match in the snippet.
     */
    private const int SNIPPET_RADIUS = 90;

    /**
     * Search one household's documents, newest document date first.
     *
     * @return Collection<int, SearchHit>
     */
    public function search(Calendar $calendar, string $term, ?int $dosarId = null, int $limit = 50): Collection
    {
        $folded = OcrText::fold($term);

        if ($folded === '') {
            return collect();
        }

        $needle = '%'.$folded.'%';

        $documents = MedicalDocument::query()
            ->inCalendar($calendar)
            ->with(['dosar', 'pages'])
            ->where('status', '!=', DocumentStatus::Pending)
            ->when($dosarId !== null, fn (Builder $query) => $query->where('medical_dosar_id', $dosarId))
            ->where(function (Builder $query) use ($needle): void {
                $query->where('search_text', 'like', $needle)
                    ->orWhereHas('dosar', fn (Builder $dosar) => $dosar->where('search_text', 'like', $needle))
                    ->orWhereHas('pages', fn (Builder $page) => $page->where('search_text', 'like', $needle));
            })
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $documents->map(fn (MedicalDocument $document): SearchHit => $this->toHit($document, $folded));
    }

    /**
     * Turn a matching document into what the results list needs.
     */
    private function toHit(MedicalDocument $document, string $needle): SearchHit
    {
        foreach ($document->pages as $page) {
            $snippet = $this->snippet((string) $page->search_text, $needle);

            if ($snippet !== null) {
                return new SearchHit(
                    document: $document,
                    matchedPage: $page->page_number,
                    snippet: $snippet,
                );
            }
        }

        return new SearchHit(
            document: $document,
            matchedPage: null,
            snippet: $this->snippet($document->search_text, $needle),
        );
    }

    /**
     * Cut a window of folded text around the first match.
     *
     * The window is taken from the folded string, not the original, because the
     * two are not always the same length: Romanian folds one character to one,
     * but "ß" expands to two. Slicing the folded text keeps the match centred
     * and never misaligns.
     */
    private function snippet(string $haystack, string $needle): ?string
    {
        $position = mb_strpos($haystack, $needle);

        if ($position === false) {
            return null;
        }

        $start = max(0, $position - self::SNIPPET_RADIUS);
        $length = self::SNIPPET_RADIUS * 2 + mb_strlen($needle);

        $snippet = mb_substr($haystack, $start, $length);

        return ($start > 0 ? '…' : '').trim($snippet).(mb_strlen($haystack) > $start + $length ? '…' : '');
    }
}
