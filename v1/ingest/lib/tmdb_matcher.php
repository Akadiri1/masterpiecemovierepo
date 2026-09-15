<?php
/**
 * Matches a scraped archive.org title onto a TMDB movie record.
 *
 * archive.org titles are entered by uploaders and are messy -- they carry
 * years, director credits, reel numbers and bracketed notes. We normalise
 * aggressively, then score candidates on title similarity plus year proximity.
 * Anything below the confidence threshold is parked for human review rather
 * than guessed at, because a wrong match pollutes the public catalogue.
 */

const TMDB_MATCH_ACCEPT = 0.85;   // auto-accept at or above this
const TMDB_MATCH_FLOOR  = 0.45;   // below this, treat as no match at all

/** Strip uploader noise down to a comparable title string. */
function ingest_normalise_title(string $title): string
{
    $t = strtolower(trim($title));

    // Drop bracketed and parenthesised asides: "(1968)", "[silent]", "{HD}"
    $t = preg_replace('/[\(\[\{][^\)\]\}]*[\)\]\}]/', ' ', $t);

    // Drop trailing credits after a dash: "the general - buster keaton"
    $t = preg_replace('/\s+[-–—]\s+.*$/', '', $t);

    // Drop reel/part markers that IA uses for split scans.
    $t = preg_replace('/\b(reel|part|disc|tape)\s*\d+\b/', ' ', $t);

    // Drop a bare trailing year.
    $t = preg_replace('/\b(1[89]\d{2}|20\d{2})\b/', ' ', $t);

    // Leading article, so "the general" and "general" compare equal.
    $t = preg_replace('/^(the|a|an)\s+/', '', $t);

    // Punctuation to spaces, then collapse.
    $t = preg_replace('/[^a-z0-9]+/', ' ', $t);

    return trim(preg_replace('/\s+/', ' ', $t));
}

/** 0..1 similarity between two raw titles. */
function ingest_title_similarity(string $a, string $b): float
{
    $a = ingest_normalise_title($a);
    $b = ingest_normalise_title($b);

    if ($a === '' || $b === '') {
        return 0.0;
    }
    if ($a === $b) {
        return 1.0;
    }

    similar_text($a, $b, $percent);
    $score = $percent / 100;

    // Levenshtein guards against similar_text over-rewarding shared letters
    // in short titles ("cat" vs "act").
    $maxLen = max(strlen($a), strlen($b));
    if ($maxLen > 0 && $maxLen < 255) {
        $lev = 1 - (levenshtein($a, $b) / $maxLen);
        $score = ($score + max($lev, 0)) / 2;
    }

    return round($score, 3);
}

/** 0..1 score for how close two release years are. */
function ingest_year_score(?int $sourceYear, ?string $tmdbDate): float
{
    if (!$sourceYear || !$tmdbDate || !preg_match('/^(\d{4})/', $tmdbDate, $m)) {
        return 0.0;
    }
    $diff = abs($sourceYear - (int) $m[1]);
    if ($diff === 0) return 1.0;
    if ($diff === 1) return 0.7;
    if ($diff <= 3)  return 0.4;
    return 0.0;
}

/**
 * Find the best TMDB match for a scraped title.
 *
 * @return array|null {tmdb_id, tmdb_title, confidence, release_date, decision}
 */
function ingest_match_tmdb(string $title, ?int $year): ?array
{
    $cleanTitle = ingest_normalise_title($title);
    if ($cleanTitle === '') {
        return null;
    }

    $params = ['query' => $cleanTitle, 'include_adult' => 'false'];
    if ($year) {
        $params['year'] = $year;
    }

    $result = fetchTmdbApi('search/movie', $params, 604800); // cache a week
    $candidates = $result['results'] ?? [];

    // A year-filtered search can come back empty for a mis-dated upload;
    // retry once without the year before giving up.
    if (empty($candidates) && $year) {
        unset($params['year']);
        $result = fetchTmdbApi('search/movie', $params, 604800);
        $candidates = $result['results'] ?? [];
    }

    if (empty($candidates)) {
        return null;
    }

    $best = null;
    $bestScore = -1.0;

    foreach (array_slice($candidates, 0, 10) as $c) {
        $titleScore = ingest_title_similarity($title, (string) ($c['title'] ?? ''));

        // Also try the original-language title, for foreign silents.
        if (!empty($c['original_title'])) {
            $titleScore = max($titleScore, ingest_title_similarity($title, (string) $c['original_title']));
        }

        $yearScore = ingest_year_score($year, $c['release_date'] ?? null);

        // Without a source year we cannot corroborate, so cap the ceiling
        // and let a human confirm.
        $confidence = $year
            ? ($titleScore * 0.75) + ($yearScore * 0.25)
            : $titleScore * 0.80;

        if ($confidence > $bestScore) {
            $bestScore = $confidence;
            $best = $c;
        }
    }

    if (!$best || $bestScore < TMDB_MATCH_FLOOR) {
        return null;
    }

    return [
        'tmdb_id'      => (int) $best['id'],
        'tmdb_title'   => (string) ($best['title'] ?? ''),
        'release_date' => $best['release_date'] ?? null,
        'confidence'   => round($bestScore, 3),
        'decision'     => $bestScore >= TMDB_MATCH_ACCEPT ? 'matched' : 'needs_review',
    ];
}
