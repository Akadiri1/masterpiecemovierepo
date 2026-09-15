<?php
include APP_PATH . '/views/includes/header.php';

// ==========================================
// 1. INITIALIZE & FETCH DATA
// ==========================================
$personId = (int) ($_GET['id'] ?? 0);

if ($personId <= 0) {
    echo "<script>window.location.href = '/';</script>";
    exit;
}

// Person details plus every credit and their social profiles, in one request.
$person = fetchTmdbApi("person/{$personId}", [
    'append_to_response' => 'combined_credits,external_ids'
]);

if (!$person) {
    echo "<div class='container p-5 text-center text-white'><h2>Person not found.</h2></div>";
    include APP_PATH . '/views/includes/footer.php';
    exit;
}

// ==========================================
// 2. PROCESS VARIABLES
// ==========================================
$name = $person['name'];
$bio = trim($person['biography'] ?? '');
$department = $person['known_for_department'] ?? '';
$placeOfBirth = $person['place_of_birth'] ?? '';

$birthdayRaw = $person['birthday'] ?? null;
$deathdayRaw = $person['deathday'] ?? null;
$birthday = $birthdayRaw ? date('F j, Y', strtotime($birthdayRaw)) : null;
$deathday = $deathdayRaw ? date('F j, Y', strtotime($deathdayRaw)) : null;
$age = null;
if ($birthdayRaw) {
    try {
        $age = (new DateTime($birthdayRaw))->diff(new DateTime($deathdayRaw ?: 'now'))->y;
    } catch (Exception $e) {
        $age = null;
    }
}

$image = !empty($person['profile_path'])
    ? 'https://image.tmdb.org/t/p/h632' . $person['profile_path']
    : '/assets/images/media/cast-placeholder.webp';

// Official profiles TMDB knows about.
$externalIds = $person['external_ids'] ?? [];
$externalLinks = [];
if (!empty($externalIds['imdb_id']))      $externalLinks[] = ['IMDb', 'https://www.imdb.com/name/' . rawurlencode($externalIds['imdb_id']), 'ph-film-slate'];
if (!empty($externalIds['instagram_id'])) $externalLinks[] = ['Instagram', 'https://www.instagram.com/' . rawurlencode($externalIds['instagram_id']), 'ph-instagram-logo'];
if (!empty($externalIds['twitter_id']))   $externalLinks[] = ['X', 'https://x.com/' . rawurlencode($externalIds['twitter_id']), 'ph-x-logo'];
if (!empty($externalIds['facebook_id']))  $externalLinks[] = ['Facebook', 'https://www.facebook.com/' . rawurlencode($externalIds['facebook_id']), 'ph-facebook-logo'];
if (!empty($externalIds['tiktok_id']))    $externalLinks[] = ['TikTok', 'https://www.tiktok.com/@' . rawurlencode($externalIds['tiktok_id']), 'ph-tiktok-logo'];

// ==========================================
// 3. PROCESS CREDITS (Filmography)
// ==========================================
// One entry per title: a show someone appeared in several times is listed
// once, with the roles joined.
$byTitle = [];
foreach ($person['combined_credits']['cast'] ?? [] as $credit) {
    $type = $credit['media_type'] ?? '';
    if ($type !== 'movie' && $type !== 'tv') {
        continue;
    }
    $key = $type . ':' . $credit['id'];
    $character = trim((string) ($credit['character'] ?? ''));

    if (isset($byTitle[$key])) {
        if ($character !== '' && stripos($byTitle[$key]['character'], $character) === false) {
            $byTitle[$key]['character'] = ltrim($byTitle[$key]['character'] . ' / ' . $character, ' /');
        }
        continue;
    }

    $date = $credit['release_date'] ?? $credit['first_air_date'] ?? '';
    $byTitle[$key] = [
        'id'         => (int) $credit['id'],
        'type'       => $type,
        'title'      => $credit['title'] ?? $credit['name'] ?? 'Untitled',
        'poster'     => !empty($credit['poster_path']) ? 'https://image.tmdb.org/t/p/w342' . $credit['poster_path'] : '/assets/images/media/placeholder-portrait.svg',
        'has_poster' => !empty($credit['poster_path']),
        'date'       => $date,
        'year'       => $date ? substr($date, 0, 4) : '',
        'character'  => $character,
        'votes'      => (int) ($credit['vote_count'] ?? 0),
    ];
}

// Newest first; titles without a date go last.
$credits = array_values($byTitle);
usort($credits, fn($a, $b) => strcmp($b['date'] ?: '0000', $a['date'] ?: '0000'));

$movieCredits = array_values(array_filter($credits, fn($c) => $c['type'] === 'movie'));
$tvCredits = array_values(array_filter($credits, fn($c) => $c['type'] === 'tv'));

// "Known for": their most-voted titles with a poster, leaving out talk shows
// and ceremonies where they appeared as themselves.
$knownFor = array_filter($credits, fn($c) => $c['has_poster'] && !preg_match('/\b(self|himself|herself|themselves)\b/i', $c['character']));
usort($knownFor, fn($a, $b) => $b['votes'] <=> $a['votes']);
$knownFor = array_slice($knownFor, 0, 10);

if (!function_exists('personCreditCard')) {
    /** A poster card linking to the title's page. */
    function personCreditCard(array $c, bool $hiddenAtFirst = false): string
    {
        $role = $c['character'] !== '' ? 'as ' . $c['character'] : ($c['type'] === 'tv' ? 'TV show' : 'Movie');
        $year = $c['year'] !== '' ? '<span class="pp-year">' . htmlspecialchars($c['year']) . '</span>' : '';
        return '<a class="pp-card' . ($hiddenAtFirst ? ' is-extra' : '') . '" href="/' . $c['type'] . '/' . $c['id'] . '">'
            . '<span class="pp-poster"><img src="' . htmlspecialchars($c['poster']) . '" alt="" loading="lazy" decoding="async">' . $year . '</span>'
            . '<span class="pp-card-title">' . htmlspecialchars($c['title']) . '</span>'
            . '<span class="pp-card-role">' . htmlspecialchars($role) . '</span>'
            . '</a>';
    }
}

// Long filmographies show this many titles until "Show all" is pressed.
$firstBatch = 18;
$filmographyTabs = [
    ['id' => 'filmo-movies', 'label' => 'Movies',   'noun' => 'movies',   'items' => $movieCredits, 'empty' => 'No movie credits found.'],
    ['id' => 'filmo-tv',     'label' => 'TV Shows', 'noun' => 'TV shows', 'items' => $tvCredits,    'empty' => 'No TV show credits found.'],
];
?>

<!-- ==========================================
     PERSON PAGE STYLES
     Mobile first: portrait beside the name and key facts, then the
     biography, their best-known titles, and the full filmography.
     ========================================== -->
<style>
    .pp {
        --pp-line: rgba(255, 255, 255, 0.08);
        --pp-muted: #9a9aa8;
        --pp-accent: var(--primary, #e50914);
        max-width: 1240px;
        margin: 0 auto;
        padding: 16px 16px 32px;
        color: #fff;
    }
    @media (min-width: 768px) { .pp { padding: 28px 24px 56px; } }

    /* 1. Header */
    .pp-hero { position: relative; overflow: hidden; border-radius: 20px; border: 1px solid var(--pp-line); background: #101018; }
    .pp-hero-bg { position: absolute; inset: 0; background-size: cover; background-position: center 20%; opacity: 0.35; filter: blur(28px) saturate(1.2); transform: scale(1.25); }
    .pp-hero::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(16, 16, 24, 0.15), #101018 88%); }
    .pp-hero-inner { position: relative; z-index: 1; display: flex; align-items: flex-end; gap: 16px; padding: 20px; }
    .pp-portrait { flex-shrink: 0; width: 112px; aspect-ratio: 2 / 3; overflow: hidden; border-radius: 14px; border: 1px solid var(--pp-line); background: #1c1c26; box-shadow: 0 16px 36px rgba(0, 0, 0, 0.5); }
    .pp-portrait img { display: block; width: 100%; height: 100%; object-fit: cover; }
    .pp-intro { flex: 1; min-width: 0; }
    .pp-breadcrumb { display: none; gap: 6px; margin: 0 0 10px; padding: 0; list-style: none; color: var(--pp-muted); font-size: 0.8rem; }
    .pp-breadcrumb a { color: var(--pp-muted); text-decoration: none; }
    .pp-breadcrumb a:hover { color: #fff; }
    .pp-breadcrumb li + li::before { content: '/'; margin-right: 6px; opacity: 0.5; }
    .pp-eyebrow { margin: 0 0 4px; color: var(--pp-accent); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
    .pp-name { margin: 0; color: #fff; font-size: 1.6rem; font-weight: 800; line-height: 1.1; letter-spacing: -0.4px; overflow-wrap: break-word; }
    .pp-facts { display: flex; flex-direction: column; gap: 4px; margin: 10px 0 0; padding: 0; list-style: none; color: #cfcfd8; font-size: 0.85rem; }
    .pp-facts li { display: flex; align-items: flex-start; gap: 6px; }
    .pp-facts i { margin-top: 2px; color: var(--pp-muted); font-size: 1rem; }
    .pp-dim { color: var(--pp-muted); }
    .pp-links { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .pp a.pp-link { display: inline-flex; align-items: center; gap: 6px; min-height: 0; padding: 5px 10px; border-radius: 999px; border: 1px solid var(--pp-line); background: rgba(255, 255, 255, 0.06); color: #ddd; font-size: 0.76rem; font-weight: 600; text-decoration: none; }
    .pp a.pp-link:hover { border-color: var(--pp-accent); color: #fff; }
    .pp-stats { position: relative; z-index: 1; display: grid; grid-template-columns: repeat(3, 1fr); border-top: 1px solid var(--pp-line); }
    .pp-stat { padding: 12px 8px; text-align: center; }
    .pp-stat + .pp-stat { border-left: 1px solid var(--pp-line); }
    .pp-stat strong { display: block; font-size: 1.1rem; font-weight: 800; }
    .pp-stat span { color: var(--pp-muted); font-size: 0.7rem; letter-spacing: 0.06em; text-transform: uppercase; }

    /* Small phones: portrait above the name, so long names get the full width
       instead of breaking mid-word beside the picture. */
    @media (max-width: 575.98px) {
        .pp-hero-inner { flex-direction: column; align-items: center; gap: 14px; padding: 24px 20px 20px; text-align: center; }
        .pp-portrait { width: 124px; }
        .pp-facts { align-items: center; }
        .pp-links { justify-content: center; }
    }

    @media (min-width: 768px) {
        .pp-hero-inner { align-items: center; gap: 28px; padding: 28px; }
        .pp-portrait { width: 190px; }
        .pp-breadcrumb { display: flex; }
        .pp-name { font-size: 2.6rem; }
        .pp-facts { flex-direction: row; flex-wrap: wrap; gap: 6px 18px; font-size: 0.95rem; }
    }

    /* 2. Sections */
    .pp-section { margin-top: 28px; }
    .pp-heading { margin: 0 0 12px; color: #fff; font-size: 1.2rem; font-weight: 700; }
    .pp-bio { color: #c9c9d3; font-size: 0.95rem; line-height: 1.7; white-space: pre-line; }
    /* Fade out instead of a line clamp: with paragraph breaks, a clamp left
       its "..." alone on an empty line. */
    .pp-bio.is-clamped { max-height: 10.2em; overflow: hidden; -webkit-mask-image: linear-gradient(#000 55%, transparent); mask-image: linear-gradient(#000 55%, transparent); }
    .pp-more { margin-top: 6px; padding: 0; border: 0; background: none; color: #fff; font-size: 0.9rem; font-weight: 600; cursor: pointer; }
    .pp-more:hover { color: var(--pp-accent); }
    .pp-empty { margin: 0; padding: 8px 0; color: var(--pp-muted); }

    /* 3. Poster cards: the "Known for" row and the filmography grid */
    .pp-row { display: flex; gap: 12px; padding-bottom: 4px; overflow-x: auto; scrollbar-width: none; scroll-snap-type: x proximity; }
    .pp-row::-webkit-scrollbar { display: none; }
    .pp-row .pp-card { flex: 0 0 118px; scroll-snap-align: start; }
    @media (min-width: 768px) { .pp-row .pp-card { flex-basis: 150px; } }
    .pp-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px 10px; }
    @media (min-width: 576px) { .pp-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 20px 14px; } }
    @media (min-width: 992px) { .pp-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
    .pp a.pp-card { display: block; min-width: 0; min-height: 0; color: inherit; text-decoration: none; }
    .pp-poster { position: relative; display: block; aspect-ratio: 2 / 3; overflow: hidden; border-radius: 12px; background: #1a1a24; }
    .pp-poster img { display: block; width: 100%; height: 100%; object-fit: cover; transition: transform 0.35s; }
    .pp-card:hover .pp-poster img { transform: scale(1.04); }
    .pp-year { position: absolute; left: 6px; top: 6px; padding: 2px 6px; border-radius: 6px; background: rgba(0, 0, 0, 0.65); color: #fff; font-size: 0.66rem; font-weight: 700; backdrop-filter: blur(4px); }
    .pp-card-title { display: block; margin-top: 8px; overflow: hidden; color: #fff; font-size: 0.82rem; font-weight: 600; white-space: nowrap; text-overflow: ellipsis; }
    .pp-card-role { display: block; overflow: hidden; color: var(--pp-muted); font-size: 0.72rem; white-space: nowrap; text-overflow: ellipsis; }
    .pp a.pp-card.is-extra { display: none; }
    .pp .pp-grid.is-expanded a.pp-card.is-extra { display: block; }
    .pp-show-all { display: flex; margin: 18px auto 0; padding: 10px 18px; border-radius: 12px; border: 1px solid var(--pp-line); background: rgba(255, 255, 255, 0.05); color: #fff; font-size: 0.9rem; font-weight: 600; }
    .pp-show-all:hover { background: rgba(255, 255, 255, 0.1); }

    /* 4. Filmography switch */
    .filmo-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 16px; margin-bottom: 18px; }
    .filmo-title { margin: 0; color: #fff; font-size: 1.2rem; font-weight: 700; }
    .filmo-tabs { display: inline-flex; flex-wrap: nowrap; gap: 4px; margin: 0; padding: 4px; list-style: none; border-radius: 999px; border: 1px solid var(--pp-line); background: rgba(255, 255, 255, 0.05); }
    .filmo-tabs .nav-link { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 0; padding: 8px 16px; border: 0; border-radius: 999px; background: transparent; color: var(--pp-muted); font-size: 0.875rem; font-weight: 600; white-space: nowrap; transition: background 0.2s, color 0.2s; }
    .filmo-tabs .nav-link:hover { color: #fff; }
    /* !important: the header's theme styles force active menu links to the brand colour as text. */
    .filmo-tabs .nav-link.active { background: var(--pp-accent); color: #fff !important; }
    .filmo-count { padding: 1px 7px; border-radius: 999px; background: rgba(255, 255, 255, 0.1); font-size: 0.72rem; font-weight: 700; }
    .filmo-tabs .nav-link.active .filmo-count { background: rgba(0, 0, 0, 0.2); }
    @media (max-width: 767.98px) {
        .filmo-tabs { display: flex; width: 100%; }
        .filmo-tabs .nav-item { flex: 1; }
        .filmo-tabs .nav-link { width: 100%; min-height: 40px; }
    }
</style>

<!-- ==========================================
     HTML CONTENT
     ========================================== -->
<div class="pp">

    <!-- 1. HEADER -->
    <section class="pp-hero">
        <div class="pp-hero-bg" style="background-image: url('<?php echo htmlspecialchars($image); ?>');" aria-hidden="true"></div>
        <div class="pp-hero-inner">
            <div class="pp-portrait">
                <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($name); ?>">
            </div>
            <div class="pp-intro">
                <ol class="pp-breadcrumb" aria-label="Breadcrumb">
                    <li><a href="/">Home</a></li>
                    <li>People</li>
                    <li aria-current="page"><?php echo htmlspecialchars($name); ?></li>
                </ol>
                <?php if ($department): ?>
                <p class="pp-eyebrow"><?php echo htmlspecialchars($department); ?></p>
                <?php endif; ?>
                <h1 class="pp-name"><?php echo htmlspecialchars($name); ?></h1>
                <ul class="pp-facts">
                    <?php if ($birthday): ?>
                    <li><i class="ph ph-cake"></i><span>Born <?php echo $birthday; ?><?php if ($age !== null && !$deathday): ?> <span class="pp-dim">(age <?php echo $age; ?>)</span><?php endif; ?></span></li>
                    <?php endif; ?>
                    <?php if ($deathday): ?>
                    <li><i class="ph ph-flower-lotus"></i><span>Died <?php echo $deathday; ?><?php if ($age !== null): ?> <span class="pp-dim">(aged <?php echo $age; ?>)</span><?php endif; ?></span></li>
                    <?php endif; ?>
                    <?php if ($placeOfBirth): ?>
                    <li><i class="ph ph-map-pin"></i><span><?php echo htmlspecialchars($placeOfBirth); ?></span></li>
                    <?php endif; ?>
                </ul>
                <?php if ($externalLinks): ?>
                <div class="pp-links">
                    <?php foreach ($externalLinks as [$label, $url, $icon]): ?>
                    <a class="pp-link" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener"><i class="ph <?php echo $icon; ?>"></i><?php echo $label; ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="pp-stats">
            <div class="pp-stat"><strong><?php echo count($credits); ?></strong><span>Credits</span></div>
            <div class="pp-stat"><strong><?php echo count($movieCredits); ?></strong><span>Movies</span></div>
            <div class="pp-stat"><strong><?php echo count($tvCredits); ?></strong><span>TV shows</span></div>
        </div>
    </section>

    <!-- 2. BIOGRAPHY -->
    <section class="pp-section">
        <h2 class="pp-heading">Biography</h2>
        <?php if ($bio !== ''): ?>
        <div class="pp-bio is-clamped" id="ppBio"><?php echo htmlspecialchars($bio); ?></div>
        <button type="button" class="pp-more" id="ppBioMore" hidden>Read more</button>
        <?php else: ?>
        <p class="pp-empty">We don't have a biography for <?php echo htmlspecialchars($name); ?> yet.</p>
        <?php endif; ?>
    </section>

    <!-- 3. KNOWN FOR -->
    <?php if ($knownFor): ?>
    <section class="pp-section">
        <h2 class="pp-heading">Known for</h2>
        <div class="pp-row">
            <?php foreach ($knownFor as $credit) echo personCreditCard($credit); ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- 4. FILMOGRAPHY -->
    <section class="pp-section">
        <div class="filmo-head">
            <h2 class="filmo-title">Filmography</h2>
            <ul class="nav filmo-tabs" role="tablist">
                <?php foreach ($filmographyTabs as $i => $tab): ?>
                <li class="nav-item" role="presentation">
                    <a class="nav-link<?php echo $i === 0 ? ' active' : ''; ?>" data-bs-toggle="pill" href="#<?php echo $tab['id']; ?>" role="tab" aria-controls="<?php echo $tab['id']; ?>" aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>">
                        <?php echo $tab['label']; ?> <span class="filmo-count"><?php echo count($tab['items']); ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="tab-content">
            <?php foreach ($filmographyTabs as $i => $tab): ?>
            <div id="<?php echo $tab['id']; ?>" class="tab-pane fade<?php echo $i === 0 ? ' show active' : ''; ?>" role="tabpanel">
                <?php if ($tab['items']): ?>
                <div class="pp-grid" id="<?php echo $tab['id']; ?>-grid">
                    <?php foreach ($tab['items'] as $n => $credit) echo personCreditCard($credit, $n >= $firstBatch); ?>
                </div>
                <?php if (count($tab['items']) > $firstBatch): ?>
                <button type="button" class="pp-show-all" data-grid="<?php echo $tab['id']; ?>-grid">Show all <?php echo count($tab['items']) . ' ' . $tab['noun']; ?></button>
                <?php endif; ?>
                <?php else: ?>
                <p class="pp-empty"><?php echo $tab['empty']; ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<script>
(function () {
    // Biography: offer "Read more" only when the text is actually cut off.
    var bio = document.getElementById('ppBio');
    var more = document.getElementById('ppBioMore');
    if (bio && more) {
        if (bio.scrollHeight > bio.clientHeight + 2) more.hidden = false;
        more.addEventListener('click', function () {
            var clamped = bio.classList.toggle('is-clamped');
            more.textContent = clamped ? 'Read more' : 'Show less';
        });
    }

    // Filmography: reveal the rest of a long list.
    document.querySelectorAll('.pp-show-all').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById(button.dataset.grid).classList.add('is-expanded');
            button.remove();
        });
    });
})();
</script>

<?php include APP_PATH . '/views/includes/footer.php'; ?>
