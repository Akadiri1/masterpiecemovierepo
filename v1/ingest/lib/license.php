<?php
/**
 * Licence gate for ingested media.
 *
 * Nothing reaches the transcode queue unless we can point at an explicit,
 * machine-readable grant of redistribution rights. Collection membership on
 * archive.org is treated as a hint only -- collections like `feature_films`
 * are curated loosely and do contain items with murky rights.
 *
 * Two project-specific rules are enforced here:
 *
 *  1. NonCommercial (-nc-) licences are rejected while INGEST_COMMERCIAL_USE
 *     is true, because this catalogue sits behind paid subscription plans.
 *
 *  2. NoDerivatives (-nd-) licences are sent to review rather than accepted,
 *     because the pipeline transcodes into a multi-bitrate ladder and a
 *     transcode is plausibly a derivative work.
 */

/** Collections that lean public domain, but are not proof on their own. */
const INGEST_PD_COLLECTION_HINTS = [
    'prelinger',
    'feature_films',
    'film_noir',
    'silent_films',
    'classic_cartoons',
    'more_animation',
    'sci-fi_horror',
    'classic_tv',
    'universal_library',
];

function ingest_normalise_license_url(?string $url): string
{
    if ($url === null) {
        return '';
    }
    $url = strtolower(trim($url));
    $url = preg_replace('#^https?://#', '', $url);
    return rtrim($url, '/');
}

/**
 * Classify an archive.org metadata blob's redistribution rights.
 *
 * @param array $meta           The `metadata` object from /metadata/{id}
 * @param bool  $commercialUse  Whether the catalogue is distributed commercially
 * @return array{decision:string,label:string,url:?string,reason:string}
 */
function ingest_classify_license(array $meta, bool $commercialUse = true): array
{
    $rawUrl  = $meta['licenseurl'] ?? null;
    $url     = ingest_normalise_license_url(is_array($rawUrl) ? ($rawUrl[0] ?? null) : $rawUrl);
    $rights  = strtolower(trim((string) ($meta['rights'] ?? '')));

    $reject = function (string $label, string $reason) use ($rawUrl): array {
        return ['decision' => 'reject', 'label' => $label, 'url' => $rawUrl, 'reason' => $reason];
    };
    $review = function (string $label, string $reason) use ($rawUrl): array {
        return ['decision' => 'review', 'label' => $label, 'url' => $rawUrl, 'reason' => $reason];
    };
    $accept = function (string $label, string $reason) use ($rawUrl): array {
        return ['decision' => 'accept', 'label' => $label, 'url' => $rawUrl, 'reason' => $reason];
    };

    if ($url !== '') {
        // Public domain dedications: unrestricted.
        if (strpos($url, 'creativecommons.org/publicdomain/mark') !== false) {
            return $accept('public_domain', 'CC Public Domain Mark');
        }
        if (strpos($url, 'creativecommons.org/publicdomain/zero') !== false) {
            return $accept('cc0', 'CC0 public domain dedication');
        }

        // Legacy CC dedication URL. This predates the Public Domain Mark and
        // is still what most archive.org items carry, so it must be checked
        // before the general licences/ branch or it gets mislabelled.
        if (strpos($url, 'creativecommons.org/licenses/publicdomain') !== false) {
            return $accept('public_domain', 'CC public domain dedication (legacy URL form)');
        }

        if (strpos($url, 'creativecommons.org/licenses/') !== false) {
            $isNc = strpos($url, '-nc') !== false;
            $isNd = strpos($url, '-nd') !== false;

            if ($isNc && $commercialUse) {
                return $reject(
                    'cc-nc',
                    'NonCommercial licence cannot be distributed behind paid plans'
                );
            }
            if ($isNd) {
                return $review(
                    'cc-nd',
                    'NoDerivatives licence: transcoding may create a derivative work'
                );
            }

            // Remaining permitted family: by, by-sa (and nc-variants when
            // the catalogue is free).
            if (preg_match('#licenses/([a-z\-]+)/#', $url . '/', $m)) {
                return $accept('cc-' . $m[1], 'Creative Commons ' . strtoupper($m[1]));
            }
            return $accept('cc', 'Creative Commons licence permitting redistribution');
        }

        // A licence URL we do not recognise: a human should look at it.
        return $review('unrecognised', 'Unrecognised licence URL: ' . $rawUrl);
    }

    // No licence URL. Fall back to the free-text rights field.
    if ($rights !== '') {
        if (preg_match('/public domain/i', $rights)) {
            return $review('claimed_public_domain', 'Rights text claims public domain, unverified');
        }
        return $review('rights_text_only', 'Only free-text rights present');
    }

    // Nothing but a collection hint.
    $collections = (array) ($meta['collection'] ?? []);
    $collections = array_map('strtolower', array_filter($collections, 'is_string'));
    $hit = array_intersect($collections, INGEST_PD_COLLECTION_HINTS);
    if (!empty($hit)) {
        return $review(
            'collection_hint',
            'In public-domain-leaning collection (' . implode(', ', $hit) . ') but no licence stated'
        );
    }

    return $reject('unknown', 'No licence URL, rights statement, or known collection');
}
