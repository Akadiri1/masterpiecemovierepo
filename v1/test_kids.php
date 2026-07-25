<?php
require 'c:/wamp64/www/masterpiecemovie/v1/config.php';
require 'c:/wamp64/www/masterpiecemovie/v1/controllers/controller.php';

function isSafeForKidsLocal(array $details): bool {
    // 1. Immediately reject explicitly adult content
    if (!empty($details['adult'])) return false;

    // 2. Reject mature genres (Horror: 27, Crime: 80, Thriller: 53, War: 10768)
    $adultGenres = [27, 80, 53, 10768];
    if (!empty($details['genre_ids'])) {
        foreach ($details['genre_ids'] as $gId) {
            if (in_array($gId, $adultGenres)) return false;
        }
    }
    if (!empty($details['genres'])) {
        foreach ($details['genres'] as $g) {
            if (in_array($g['id'], $adultGenres)) return false;
        }
    }

    $hasSafeCert = false;
    $hasMatureCert = false;
    $safeCerts = ['G', 'PG', 'PG-13', 'TV-Y', 'TV-Y7', 'TV-G', 'TV-PG', 'TV-14', 'U', 'U/A', 'A', 'ALL'];

    // 3. Check Movie Certifications globally
    if (!empty($details['release_dates']['results'])) {
        foreach ($details['release_dates']['results'] as $result) {
            foreach ($result['release_dates'] as $r) {
                $cert = strtoupper(trim((string)($r['certification'] ?? '')));
                if ($cert === '') continue;
                
                if (preg_match('/\b(15|16|18|21|NC-17|R|X|TV-MA|MA|NR|UR)\b/i', $cert)) {
                    $hasMatureCert = true;
                } elseif (in_array($cert, $safeCerts)) {
                    $hasSafeCert = true;
                }
            }
        }
    }

    // 4. Check TV Certifications globally
    if (!empty($details['content_ratings']['results'])) {
        foreach ($details['content_ratings']['results'] as $result) {
            $rating = strtoupper(trim((string)($result['rating'] ?? '')));
            if ($rating === '') continue;
            
            if (preg_match('/\b(15|16|18|21|NC-17|R|X|TV-MA|MA|NR|UR)\b/i', $rating)) {
                $hasMatureCert = true;
            } elseif (in_array($rating, $safeCerts)) {
                $hasSafeCert = true;
            }
        }
    }

    // 5. Final Decision
    if ($hasMatureCert) return false;        // If we found a safe certification, allow it.
    if ($hasSafeCert) return true;

    // If there are no certifications at all, we've already verified it doesn't have 
    // explicitly mature genres (Horror, Crime, etc) or an 'adult' flag.
    // It's safer to allow it so the catalog isn't completely empty for older/unrated titles.
    return true;
}

$out = "";
$ids = [217288, 127532, 7225];
foreach ($ids as $id) {
    $details = fetchTmdbApi("movie/" . $id, ['append_to_response' => 'release_dates,content_ratings']);
    if (empty($details)) {
        $details = fetchTmdbApi("tv/" . $id, ['append_to_response' => 'release_dates,content_ratings']);
    }
    
    if (!empty($details) && isset($details['title'])) {
        $safe = isSafeForKidsLocal($details) ? 'YES' : 'NO';
        $out .= "ID $id ({$details['title']}) - Safe? $safe\n";
    } elseif (!empty($details) && isset($details['name'])) {
        $safe = isSafeForKidsLocal($details) ? 'YES' : 'NO';
        $out .= "ID $id (TV: {$details['name']}) - Safe? $safe\n";
    } else {
        $out .= "ID $id - Failed to fetch\n";
    }
}
file_put_contents('c:/wamp64/www/masterpiecemovie/v1/cache/test_kids.txt', $out);
?>
