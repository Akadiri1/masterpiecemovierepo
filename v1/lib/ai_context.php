<?php
/**
 * Context builders that ground ZEN AI in this site's own data.
 *
 * Two separate jobs:
 *
 *  1. zen_user_context()    -- what this viewer has actually been watching, so
 *                              recommendations are personal rather than generic.
 *  2. zen_catalog_context() -- which titles this platform genuinely holds files
 *                              for, so the model can point at real content
 *                              instead of guessing at the library.
 *
 * PRIVACY NOTE: zen_user_context() sends a viewer's recent titles to Groq as
 * part of the prompt. That is what makes the recommendations personal, but it
 * does mean viewing history leaves the server. If that is not acceptable,
 * set ZEN_PERSONALISE to false in config and the block is omitted.
 *
 * Both builders are best effort. If a table is missing or empty they return an
 * empty string, and the prompt simply carries no such section -- the model is
 * never told about data that does not exist.
 */

if (!defined('ZEN_CONTEXT_TTL')) {
    define('ZEN_CONTEXT_TTL', 900); // seconds to reuse a built context block
}

/**
 * Wall-clock budget for building a context block, in milliseconds.
 *
 * Titles are resolved through the TMDB disk cache, which makes a warm build
 * take single-digit milliseconds. A cold one has to fetch each title over the
 * network, and measured cold that took over eight seconds -- unacceptable in
 * front of a chat reply. Once the budget is spent we stop resolving and use
 * whatever was gathered: a partial personalisation hint is worth far more than
 * a viewer staring at a spinner. Each build also warms the disk cache, so the
 * next one gets further.
 */
if (!defined('ZEN_CONTEXT_BUDGET_MS')) {
    define('ZEN_CONTEXT_BUDGET_MS', 1200);
}

/** Resolve a TMDB id to a title, using the on-disk TMDB cache. */
function zen_title_for(int $tmdbId, string $mediaType): ?string
{
    if (!function_exists('fetchTmdbApi') || $tmdbId <= 0) {
        return null;
    }
    $mediaType = $mediaType === 'tv' ? 'tv' : 'movie';

    // Long TTL: titles do not change, and this keeps chat latency down.
    $d = fetchTmdbApi("{$mediaType}/{$tmdbId}", [], 2592000);
    $title = $d['title'] ?? $d['name'] ?? null;

    return $title ? trim((string) $title) : null;
}

/**
 * Build the "who is this viewer" block.
 *
 * Cached in the session for ZEN_CONTEXT_TTL so a back-and-forth conversation
 * does not re-resolve the same titles on every message.
 */
function zen_user_context(?PDO $conn, int $userId): string
{
    if (!$conn || $userId <= 0) {
        return '';
    }
    if (defined('ZEN_PERSONALISE') && ZEN_PERSONALISE === false) {
        return '';
    }

    $cacheKey = 'zen_ctx_' . $userId;
    if (isset($_SESSION[$cacheKey]['ts'], $_SESSION[$cacheKey]['text'])
        && (time() - $_SESSION[$cacheKey]['ts']) < ZEN_CONTEXT_TTL) {
        return (string) $_SESSION[$cacheKey]['text'];
    }

    $finished = [];
    $inProgress = [];
    $saved = [];

    $startedAt = microtime(true);
    $overBudget = static function () use ($startedAt): bool {
        return ((microtime(true) - $startedAt) * 1000) > ZEN_CONTEXT_BUDGET_MS;
    };

    // ---- recently watched -------------------------------------------------
    try {
        // `current_time` is a reserved word in MySQL, hence the backticks.
        $stmt = $conn->prepare("
            SELECT tmdb_movie_id, media_type, `current_time`, total_duration
              FROM watch_history
             WHERE user_id = ?
          ORDER BY last_watched DESC
             LIMIT 8
        ");
        $stmt->execute([$userId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($overBudget()) {
                break; // partial context beats a slow reply
            }
            $title = zen_title_for((int) $row['tmdb_movie_id'], (string) ($row['media_type'] ?? 'movie'));
            if (!$title) {
                continue;
            }
            $dur = (float) ($row['total_duration'] ?? 0);
            $pos = (float) ($row['current_time'] ?? 0);

            // Anything past 90% counts as watched; the rest is still open, which
            // is a much more useful signal -- the model can offer to resume it.
            if ($dur > 0 && ($pos / $dur) < 0.9 && $pos > 30) {
                $inProgress[] = $title . ' (' . round(($pos / $dur) * 100) . '% in)';
            } else {
                $finished[] = $title;
            }
        }
    } catch (PDOException $e) {
        // No watch history table: personalisation is simply skipped.
    }

    // ---- watchlist --------------------------------------------------------
    try {
        $stmt = $conn->prepare("
            SELECT tmdb_movie_id, media_type
              FROM watchlist
             WHERE user_id = ?
          ORDER BY id DESC
             LIMIT 5
        ");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($overBudget()) {
                break;
            }
            $title = zen_title_for((int) $row['tmdb_movie_id'], (string) ($row['media_type'] ?? 'movie'));
            if ($title) {
                $saved[] = $title;
            }
        }
    } catch (PDOException $e) {
    }

    $parts = [];
    if ($inProgress) {
        $parts[] = 'Part-way through: ' . implode('; ', array_slice($inProgress, 0, 3)) . '.';
    }
    if ($finished) {
        $parts[] = 'Recently watched: ' . implode(', ', array_slice($finished, 0, 6)) . '.';
    }
    if ($saved) {
        $parts[] = 'Saved for later: ' . implode(', ', $saved) . '.';
    }

    $text = '';
    if ($parts) {
        $text = "\n\nVIEWER CONTEXT (use this to personalise, never recite it back verbatim):\n"
              . implode("\n", $parts)
              . "\nPrefer suggestions that fit these tastes. Do not re-recommend something in "
              . "'Recently watched' unless the user asks for it. If something is listed as "
              . "part-way through, you may offer to continue it.";
    }

    $_SESSION[$cacheKey] = ['ts' => time(), 'text' => $text];

    return $text;
}

/**
 * Build the "what does this platform actually hold" block.
 *
 * Only titles with a real, active file are listed. While the ingestion
 * pipeline has not published anything this returns an empty string, so the
 * model is not encouraged to claim availability the site cannot back up.
 */
function zen_catalog_context(?PDO $conn, int $limit = 40): string
{
    if (!$conn) {
        return '';
    }

    $titles = [];
    try {
        $stmt = $conn->prepare("
            SELECT tmdb_id, media_type, GROUP_CONCAT(DISTINCT quality ORDER BY quality DESC) AS qualities
              FROM media_downloads
             WHERE is_active = 1
          GROUP BY tmdb_id, media_type
             LIMIT " . (int) $limit
        );
        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $title = zen_title_for((int) $row['tmdb_id'], (string) $row['media_type']);
            if ($title) {
                $titles[] = $title;
            }
        }
    } catch (PDOException $e) {
        return '';
    }

    if (!$titles) {
        return '';
    }

    return "\n\nAVAILABLE IN HIGH QUALITY ON THIS PLATFORM:\n"
         . implode(', ', $titles)
         . "\nWhen one of these fits the request, prefer it and mention that it is "
         . "available to download in high quality here. Never claim any other title "
         . "is downloadable.";
}

/**
 * Look up which of a set of results actually have files.
 *
 * @param array $items  Each with 'id' and 'type'
 * @return array Map of "<id>|<type>" => ['qualities' => '1080p,720p']
 */
function zen_availability(?PDO $conn, array $items): array
{
    if (!$conn || !$items) {
        return [];
    }

    $ids = [];
    foreach ($items as $it) {
        $id = (int) ($it['id'] ?? 0);
        if ($id > 0) {
            $ids[$id] = true;
        }
    }
    if (!$ids) {
        return [];
    }

    $ids = array_keys($ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        $stmt = $conn->prepare("
            SELECT tmdb_id, media_type,
                   GROUP_CONCAT(DISTINCT quality ORDER BY quality DESC) AS qualities
              FROM media_downloads
             WHERE is_active = 1 AND tmdb_id IN ({$placeholders})
          GROUP BY tmdb_id, media_type
        ");
        $stmt->execute($ids);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[$row['tmdb_id'] . '|' . $row['media_type']] = [
                'qualities' => (string) $row['qualities'],
            ];
        }
        return $out;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Facts about whatever the message seems to be asking about, read from TMDB.
 *
 * The model's training data is older than the catalogue, so left to its own
 * memory it gets release dates, cast and "does this exist?" wrong, and it used
 * to be told to never say a title is unknown, which turned uncertainty into
 * invention. Looking the titles up first and handing the model the facts fixes
 * both: it answers from data, and can say plainly when TMDB has nothing.
 *
 * @param string $query      what the viewer just typed
 * @param array  $lastTitles the titles the conversation was already about, for
 *                           follow-ups like "how did it end?" or "the second one"
 */
function zen_query_facts(string $query, array $lastTitles = []): string
{
    if (!function_exists('fetchTmdbApi')) {
        return '';
    }

    // Strip the question around the title: "when does dune part three come
    // out?" searches better as "dune part three".
    $term = trim(preg_replace([
        '/^(hi|hey|hello|please|pls|yo)\b[\s,]*/i',
        '/\b(when (does|is|will)|what(\'s| is)?|who (starred|stars|acted|is|are)|tell me about|how (did|does|long)|is there|can i (watch|see)|do you (know|have)|i want( to watch)?|show me|find|search( for)?|recommend|give me|play|plot of|cast of|rating of|release date( of)?)\b/i',
        '/\b(movie|film|series|show|tv show|please|about|the plot|end|ending|come out|coming out|available|on zen|for me)\b/i',
        '/[?!.,]+/',
    ], ' ', $query));
    $term = trim(preg_replace('/\s+/', ' ', $term));

    // "who is in it?" leaves "in it", which matches a real show and sends the
    // answer somewhere else entirely. Leftovers like that are dropped, and the
    // title already under discussion is used instead.
    $leftover = ['it', 'that', 'this', 'one', 'them', 'they', 'he', 'she', 'in', 'on', 'of', 'me', 'you', 'we',
                 'yes', 'no', 'ok', 'okay', 'thanks', 'thank', 'good', 'nice', 'more', 'other', 'another',
                 'so', 'and', 'but', 'too', 'also', 'again', 'now', 'there', 'here', 'like', 'any', 'some', 'a', 'an', 'the'];
    $words = array_filter(preg_split('/\s+/', mb_strtolower($term)));
    $meaningful = array_diff($words, $leftover);

    $terms = [];
    $namesATitle = $meaningful && mb_strlen($term) >= 3 && preg_match('/[a-z0-9]/i', $term);
    if ($namesATitle) {
        $terms[] = $term;
    }
    // A follow-up ("who is in it?", "tell me more about the second one") names
    // no title, so the ones just shown are looked up instead.
    foreach (array_slice($lastTitles, 0, $namesATitle ? 1 : 3) as $previous) {
        if ($previous !== '' && ($term === '' || stripos($term, $previous) === false)) {
            $terms[] = $previous;
        }
    }
    if (!$terms) {
        return '';
    }

    $lines = [];
    foreach (array_slice(array_unique($terms), 0, 2) as $search) {
        // A leading "the" throws TMDB's search off badly -- "the Sinners"
        // returns The Garden of Sinners, while "Sinners" returns the film
        // being asked about -- so both spellings are searched and merged.
        $bare = trim(preg_replace('/^(the|a|an)\s+/i', '', $search));
        $results = [];
        foreach (array_unique([$search, $bare]) as $variant) {
            foreach (fetchTmdbApi('search/multi', ['query' => $variant, 'include_adult' => 'false'], 43200)['results'] ?? [] as $item) {
                if (in_array($item['media_type'] ?? '', ['movie', 'tv'], true)) {
                    $results[$item['media_type'] . ':' . $item['id']] ??= $item;
                }
            }
        }
        $results = array_values($results);
        if (!$results) {
            $lines[] = "- Nothing on TMDB matches \"$search\", so that title may not exist.";
            continue;
        }
        // Most talked-about first, so an unqualified name lands on the film
        // people actually mean.
        usort($results, fn($a, $b) => ($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0));

        // An exact name match beats a more popular near-miss: asked about
        // "Sinners", the answer must not wander off to "Saints & Sinners".
        // "The" is ignored on both sides, and when several titles share a name
        // the best known one wins, so "the sinners" finds Sinners (2025)
        // rather than The Sinners (2020).
        $plain = static fn(string $text): string => trim(preg_replace(
            ['/^(the|a|an)\s+/i', '/[^a-z0-9 ]+/i', '/\s+/'], ['', '', ' '], mb_strtolower($text)
        ));
        $wanted = $plain($search);
        $best = $results[0];
        $bestScore = -1;
        foreach ($results as $item) {
            if ($plain((string) ($item['title'] ?? $item['name'] ?? '')) !== $wanted) {
                continue;
            }
            $score = (float) ($item['popularity'] ?? 0);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        $type = $best['media_type'] === 'tv' ? 'tv' : 'movie';
        $full = fetchTmdbApi("$type/{$best['id']}", ['append_to_response' => 'credits'], 43200) ?: $best;
        $date = $full['release_date'] ?? $full['first_air_date'] ?? '';
        $cast = implode(', ', array_slice(array_column($full['credits']['cast'] ?? [], 'name'), 0, 5));
        $directors = [];
        foreach ($full['credits']['crew'] ?? [] as $crew) {
            if (($crew['job'] ?? '') === 'Director') {
                $directors[] = $crew['name'];
            }
        }
        $overview = trim((string) ($full['overview'] ?? ''));

        $lines[] = sprintf('- THE TITLE THEY MEAN by "%s": %s (%s, %s)%s%s%s%s%s',
            $search,
            $full['title'] ?? $full['name'] ?? 'Untitled',
            $type === 'tv' ? 'TV show' : 'movie',
            $date !== '' ? $date : 'release date not set',
            !empty($full['vote_average']) ? ', rated ' . round((float) $full['vote_average'], 1) . '/10' : '',
            $directors ? '. Directed by ' . implode(' and ', array_slice($directors, 0, 2)) : '',
            $cast !== '' ? '. Starring ' . $cast : '',
            !empty($full['genres']) ? '. Genres: ' . implode(', ', array_column($full['genres'], 'name')) : '',
            $overview !== '' ? '. Story: ' . mb_substr($overview, 0, 260) . (mb_strlen($overview) > 260 ? '…' : '') : ''
        );

        foreach (array_slice(array_filter($results, fn($i) => $i['id'] !== $best['id']), 0, 2) as $other) {
            $otherDate = $other['release_date'] ?? $other['first_air_date'] ?? '';
            $lines[] = sprintf('- Also named similarly: %s (%s%s)',
                $other['title'] ?? $other['name'] ?? 'Untitled',
                ($other['media_type'] ?? '') === 'tv' ? 'TV show' : 'movie',
                $otherDate ? ', ' . substr($otherDate, 0, 4) : ''
            );
        }
    }

    if (!$lines) {
        return '';
    }
    return "\n\nFROM TMDB, LOOKED UP JUST NOW. These are current and your memory is not, so answer from them. "
         . "Answer about the title marked THE TITLE THEY MEAN, not a similarly named one, and ignore any line that "
         . "is clearly unrelated to the question:\n" . implode("\n", array_slice($lines, 0, 7));
}
