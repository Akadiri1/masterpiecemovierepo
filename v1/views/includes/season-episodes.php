<?php
/*
 * Seasons and episodes of a series: a row of season buttons, then the chosen
 * season's episodes. Used on the series details page and, on phones, under
 * the player on the watch page. Other seasons load from /season-episodes
 * when their button is pressed, so long series don't slow the page down.
 */

if (!function_exists('seasonEpisodeItems')) {
    /** One season's episode list, as HTML. */
    function seasonEpisodeItems(int $tvId, int $seasonNumber, array $episodes, ?int $currentSeason = null, ?int $currentEpisode = null): string
    {
        if (!$episodes) {
            return '<p class="se-empty">No episodes are listed for this season yet.</p>';
        }

        $today = date('Y-m-d');
        $html = '';
        foreach ($episodes as $ep) {
            $number = (int) ($ep['episode_number'] ?? 0);
            $airDate = $ep['air_date'] ?? '';
            $upcoming = $airDate !== '' && $airDate > $today;
            $isCurrent = $currentSeason === $seasonNumber && $currentEpisode === $number;
            $still = !empty($ep['still_path']) ? 'https://image.tmdb.org/t/p/w300' . $ep['still_path'] : '';

            $meta = [];
            if (!empty($ep['runtime'])) {
                $meta[] = (int) $ep['runtime'] . ' min';
            }
            if ($airDate !== '') {
                $meta[] = ($upcoming ? 'Airs ' : '') . date('M j, Y', strtotime($airDate));
            }

            // Episodes that haven't aired yet can't be opened.
            $tag = $upcoming ? 'div' : 'a';
            $link = $upcoming
                ? ' aria-disabled="true"'
                : ' href="/watch?id=' . $tvId . '&amp;type=tv&amp;season=' . $seasonNumber . '&amp;episode=' . $number . '"';

            $html .= '<' . $tag . ' class="se-item' . ($upcoming ? ' is-upcoming' : '') . ($isCurrent ? ' is-current' : '') . '"' . $link . ($isCurrent ? ' aria-current="true"' : '') . '>'
                . '<span class="se-thumb">'
                .   ($still ? '<img src="' . htmlspecialchars($still) . '" alt="" loading="lazy" decoding="async">' : '<i class="ph ph-film-slate"></i>')
                .   '<span class="se-num">E' . $number . '</span>'
                .   ($isCurrent ? '<span class="se-now">Playing</span>' : '')
                . '</span>'
                . '<span class="se-body">'
                .   '<span class="se-title">' . htmlspecialchars($ep['name'] ?? ('Episode ' . $number)) . '</span>'
                .   ($meta ? '<span class="se-meta">' . htmlspecialchars(implode(' · ', $meta)) . '</span>' : '')
                .   (!empty($ep['overview']) ? '<span class="se-overview">' . htmlspecialchars($ep['overview']) . '</span>' : '')
                . '</span>'
                . '</' . $tag . '>';
        }
        return $html;
    }
}

if (!function_exists('seasonsSection')) {
    /**
     * The whole section: heading, season buttons and the selected season's episodes.
     *
     * @param array    $seasons         "seasons" from the show's TMDB details
     * @param int      $selected        the season shown first
     * @param array    $episodes        that season's episodes
     * @param int|null $currentSeason   on the watch page, the episode playing
     * @param int|null $currentEpisode
     */
    function seasonsSection(int $tvId, array $seasons, int $selected, array $episodes, ?int $currentSeason = null, ?int $currentEpisode = null, string $extraClass = ''): string
    {
        // Regular seasons in order, then "Specials" (season 0); skip empty ones.
        $seasons = array_values(array_filter($seasons, fn($s) => (int) ($s['episode_count'] ?? 0) > 0));
        usort($seasons, function ($a, $b) {
            $x = (int) $a['season_number'];
            $y = (int) $b['season_number'];
            return ($x === 0) <=> ($y === 0) ?: $x <=> $y;
        });
        if (!$seasons) {
            return '';
        }

        $regular = count(array_filter($seasons, fn($s) => (int) $s['season_number'] > 0));
        $buttons = '';
        foreach ($seasons as $season) {
            $number = (int) $season['season_number'];
            $active = $number === $selected;
            $label = $number === 0 ? 'Specials' : 'Season ' . $number;
            $buttons .= '<button type="button" role="tab" class="se-season' . ($active ? ' is-active' : '') . '" data-season="' . $number . '" aria-selected="' . ($active ? 'true' : 'false') . '">'
                . $label . '<span>' . (int) $season['episode_count'] . '</span></button>';
        }

        $html = '';
        static $assetsIncluded = false;
        if (!$assetsIncluded) {
            $assetsIncluded = true;
            $html .= seasonsSectionAssets();
        }

        return $html
            . '<section class="se-section ' . htmlspecialchars($extraClass) . '" data-tv="' . $tvId . '"'
            .   ($currentSeason !== null ? ' data-current-season="' . $currentSeason . '" data-current-episode="' . (int) $currentEpisode . '"' : '') . '>'
            . '<div class="se-head"><h4 class="se-heading">Episodes</h4><span class="se-count">' . $regular . ' season' . ($regular === 1 ? '' : 's') . '</span></div>'
            . '<div class="se-seasons" role="tablist" aria-label="Seasons">' . $buttons . '</div>'
            . '<div class="se-list" role="tabpanel" aria-live="polite">' . seasonEpisodeItems($tvId, $selected, $episodes, $currentSeason, $currentEpisode) . '</div>'
            . '</section>';
    }

    /** Styles and script for the section, included once per page. */
    function seasonsSectionAssets(): string
    {
        return <<<'HTML'
<style>
.se-section { margin: 26px 0 8px; }
.se-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.se-heading { margin: 0; color: #fff; font-size: 1.2rem; font-weight: 700; }
.se-count { color: #8a8a96; font-size: 0.85rem; }
.se-seasons { display: flex; gap: 8px; margin: 0 0 14px; padding: 2px 0 6px; overflow-x: auto; scrollbar-width: none; }
.se-seasons::-webkit-scrollbar { display: none; }
.se-season { flex-shrink: 0; display: inline-flex; align-items: center; gap: 6px; min-height: 0; padding: 8px 14px; border-radius: 999px; border: 1px solid rgba(255, 255, 255, 0.12); background: rgba(255, 255, 255, 0.05); color: #cfcfd8; font-size: 0.85rem; font-weight: 600; white-space: nowrap; transition: background 0.2s, border-color 0.2s, color 0.2s; }
.se-season:hover { color: #fff; border-color: rgba(255, 255, 255, 0.25); }
.se-season span { padding: 0 6px; border-radius: 999px; background: rgba(255, 255, 255, 0.1); font-size: 0.7rem; }
.se-season.is-active { border-color: var(--primary, #e50914); background: var(--primary, #e50914); color: #fff; }
.se-season.is-active span { background: rgba(0, 0, 0, 0.2); }
.se-list { display: flex; flex-direction: column; gap: 10px; }
@media (min-width: 992px) { .se-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); } }
.se-item { display: flex; gap: 12px; min-height: 0; padding: 8px; border-radius: 14px; border: 1px solid rgba(255, 255, 255, 0.06); background: rgba(255, 255, 255, 0.03); color: inherit; text-decoration: none; transition: background 0.2s, border-color 0.2s; }
a.se-item:hover { border-color: rgba(255, 255, 255, 0.14); background: rgba(255, 255, 255, 0.07); color: inherit; }
.se-item.is-current { border-color: var(--primary, #e50914); background: rgba(229, 9, 20, 0.08); }
.se-item.is-upcoming { opacity: 0.55; }
.se-thumb { position: relative; flex-shrink: 0; display: grid; place-items: center; width: 132px; aspect-ratio: 16 / 9; overflow: hidden; border-radius: 10px; background: #1a1a24; color: #55555f; font-size: 1.4rem; }
.se-thumb img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.se-num { position: absolute; left: 6px; bottom: 6px; padding: 1px 6px; border-radius: 6px; background: rgba(0, 0, 0, 0.72); color: #fff; font-size: 0.68rem; font-weight: 700; }
.se-now { position: absolute; right: 6px; top: 6px; padding: 1px 6px; border-radius: 6px; background: var(--primary, #e50914); color: #fff; font-size: 0.6rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
.se-body { display: flex; flex: 1; flex-direction: column; gap: 3px; min-width: 0; padding-top: 2px; }
.se-title { display: -webkit-box; overflow: hidden; color: #fff; font-size: 0.92rem; font-weight: 600; line-height: 1.3; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.se-meta { color: #8a8a96; font-size: 0.76rem; }
.se-overview { display: -webkit-box; overflow: hidden; color: #a9a9b5; font-size: 0.8rem; line-height: 1.45; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.se-empty { margin: 0; padding: 18px 0; color: #8a8a96; text-align: center; }
.se-retry { margin-left: 6px; padding: 0; border: 0; background: none; color: #fff; font-weight: 600; text-decoration: underline; }
.se-skeleton { height: 90px; border-radius: 14px; background: #16161f linear-gradient(100deg, transparent 20%, rgba(255, 255, 255, 0.06) 50%, transparent 80%); background-size: 200% 100%; animation: se-shimmer 1.4s ease-in-out infinite; }
@keyframes se-shimmer { from { background-position: 150% 0; } to { background-position: -50% 0; } }
@media (max-width: 575.98px) { .se-thumb { width: 116px; } }
</style>
<script>
(function () {
  if (window.__seasonsReady) return;
  window.__seasonsReady = true;
  var loaded = {};

  function select(button) {
    var section = button.closest('.se-section');
    var list = section.querySelector('.se-list');
    section.querySelectorAll('.se-season').forEach(function (b) {
      var on = b === button;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
    });

    var key = section.dataset.tv + ':' + button.dataset.season;
    if (loaded[key]) {
      list.innerHTML = loaded[key];
      return;
    }
    list.innerHTML = '<div class="se-skeleton"></div><div class="se-skeleton"></div><div class="se-skeleton"></div>';

    var url = '/season-episodes?id=' + encodeURIComponent(section.dataset.tv) + '&season=' + encodeURIComponent(button.dataset.season);
    if (section.dataset.currentSeason) {
      url += '&cs=' + section.dataset.currentSeason + '&ce=' + section.dataset.currentEpisode;
    }
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.text();
      })
      .then(function (html) {
        loaded[key] = html;
        if (button.classList.contains('is-active')) list.innerHTML = html;
      })
      .catch(function () {
        if (button.classList.contains('is-active')) {
          list.innerHTML = '<p class="se-empty">Couldn\'t load this season. <button type="button" class="se-retry">Try again</button></p>';
        }
      });
  }

  document.addEventListener('click', function (event) {
    var retry = event.target.closest('.se-retry');
    if (retry) {
      var active = retry.closest('.se-section').querySelector('.se-season.is-active');
      if (active) select(active);
      return;
    }
    var button = event.target.closest('.se-season');
    if (button && !button.classList.contains('is-active')) select(button);
  });

  // Bring the selected season's button into view (e.g. season 6 of 8).
  function centreActive() {
    document.querySelectorAll('.se-section').forEach(function (section) {
      var row = section.querySelector('.se-seasons');
      var active = row && row.querySelector('.is-active');
      if (active) row.scrollLeft = active.offsetLeft - (row.clientWidth - active.offsetWidth) / 2;
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', centreActive);
  else centreActive();
})();
</script>
HTML;
    }
}
