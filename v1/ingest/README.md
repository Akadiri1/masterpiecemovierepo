# Content ingestion pipeline

Automated catalogue ingestion for MasterpieceMovie. Discovers films on
archive.org, verifies that they may legally be redistributed, picks the
highest-quality source file available, matches the title to TMDB, and queues
the result for transcoding.

## Why the sourcing works this way

The catalogue is distributed commercially (paid plans via Paystack), and the
downloads are public. That means every title needs a defensible answer to
"why are you allowed to distribute this?" The pipeline answers that per item
by recording a machine-readable licence URL alongside the file, and refusing
to queue anything that lacks one.

Two rules follow from that and are enforced in `lib/license.php`:

| Rule | Reason |
|---|---|
| NonCommercial (`-nc-`) licences are **rejected** | The catalogue sits behind paid subscription plans |
| NoDerivatives (`-nd-`) licences go to **review** | Transcoding into a quality ladder plausibly creates a derivative work |

Collection membership alone is never sufficient. `feature_films` is curated
loosely and contains items with unclear rights, so those land in `needs_review`
for a human to confirm rather than being published automatically.

## Setup

```bash
php v1/ingest/migrate.php up      # creates ingestion_jobs + ingestion_runs
php v1/ingest/migrate.php down    # drops them
```

### TLS note

WAMP ships PHP with `curl.cainfo` unset, so CLI HTTPS calls fail certificate
verification. `bootstrap.php` locates a CA bundle automatically (it looks in
`v1/ingest/cacert.pem`, the php.ini settings, and the `extras/ssl` directory of
any PHP version installed under this WAMP).

If none is found, download <https://curl.se/ca/cacert.pem> to
`v1/ingest/cacert.pem`, or set the `INGEST_CA_BUNDLE` environment variable.
Certificate verification is never disabled.

## Usage

```bash
# preview decisions without writing anything
php v1/ingest/scrape.php --collection=feature_films --rows=25 --dry-run

# ingest for real
php v1/ingest/scrape.php --collection=feature_films --pages=4

# re-evaluate items already in the table (e.g. after changing the licence rules)
php v1/ingest/scrape.php --collection=film_noir --refresh
```

| Option | Meaning |
|---|---|
| `--collection=NAME` | archive.org collection to walk (default `feature_films`) |
| `--rows=N` | items per page, max 100 (default 50) |
| `--pages=N` | pages to walk (default 1) |
| `--limit=N` | stop after N newly-seen items |
| `--dry-run` | print decisions, write nothing |
| `--refresh` | re-evaluate items already recorded |

### Collections worth walking

`feature_films`, `film_noir`, `silent_films`, `classic_cartoons`,
`more_animation`, `sci-fi_horror`, `prelinger`.

## Job states

```
discovered → needs_review → matched → queued → transcoding → published
                 ↓
             rejected / failed
```

| Status | Meaning |
|---|---|
| `matched` | Rights verified and TMDB matched confidently. Ready to transcode. |
| `needs_review` | Ambiguous licence, or TMDB match below the confidence threshold. |
| `rejected` | No redistribution rights. Recorded so it is not re-fetched. |
| `published` | Live in `media_downloads`; served by `/download`. |

Review the queue with:

```sql
SELECT source_title, quality, license_label, status_message
  FROM ingestion_jobs WHERE status = 'needs_review';
```

## Title matching

archive.org titles are uploader-entered and messy ("Nosferatu [silent]",
"The General - Buster Keaton", "Metropolis Reel 2"). `lib/tmdb_matcher.php`
normalises them, then scores candidates on title similarity (75%) and year
proximity (25%). Matches at or above `0.85` are auto-accepted; anything lower
is parked for review, because a wrong match pollutes the public catalogue.
Without a source year the ceiling is capped, so unverifiable matches always
get human eyes.

TMDB lookups go through the existing cached `fetchTmdbApi()`, so re-runs do not
re-hit the API.

## Files

| File | Role |
|---|---|
| `scrape.php` | Walks a collection and fills the queue |
| `publish.php` | Transcodes approved jobs and uploads them |
| `doctor.php` | Preflight checks for the whole pipeline |
| `migrate.php` | Creates/drops the ingestion tables |
| `bootstrap.php` | Config, PDO, TLS trust store, CLI helpers |
| `lib/license.php` | Redistribution-rights gate |
| `lib/archive_client.php` | archive.org search/metadata client, best-file picker |
| `lib/tmdb_matcher.php` | Title normalisation and TMDB matching |
| `lib/s3.php` | SigV4 signing and uploads (Wasabi/S3) |
| `lib/transcode.php` | ffmpeg/ffprobe wrappers and the quality ladder |
| `lib/publish_lib.php` | Resumable download + `media_downloads` upsert |

## Storage setup

Fill in the `WASABI_*` values at the bottom of `.env/config.php` (gitignored),
install ffmpeg, then confirm everything is wired up:

```bash
php v1/ingest/doctor.php
```

It checks PHP extensions, the TLS trust store, all required tables, ffmpeg and
ffprobe, free disk space, and authenticates against the bucket with a HEAD on a
key that should not exist — so it proves the credentials work without writing
anything. Every failure prints the fix.

## Publishing

```bash
php v1/ingest/publish.php --limit=1 --dry-run   # show the plan only
php v1/ingest/publish.php --limit=1             # do it
php v1/ingest/publish.php --job=11              # one specific job
```

| Option | Meaning |
|---|---|
| `--limit=N` | process at most N jobs (default 1, smallest source first) |
| `--job=ID` | process one job regardless of status |
| `--dry-run` | print the plan; no transcode, no upload, no writes |
| `--keep-temp` | leave staged renditions on disk |
| `--quiet` | suppress ffmpeg's per-frame progress |

For each approved job the worker downloads the source (resuming a partial file
if one is present), probes it, transcodes the ladder, uploads each rendition to
`movies/{tmdb_id}/{quality}.mp4`, and upserts a `media_downloads` row so
`/download` serves it. Re-publishing a title replaces its links rather than
stacking duplicates.

**The ladder never upscales.** A 480p source yields a 480p rendition only;
inventing pixels would cost storage and bandwidth while making the download
look worse than the honest original.

Failures set the job to `failed` with the reason in `status_message` and
increment `attempts`, so a title can be retried with `--job=ID` once the cause
is fixed.

## Attribution

`public_domain` and `cc0` titles carry no attribution requirement. `cc-by` and
`cc-by-sa` do, so the creator captured at scrape time travels through to
`media_downloads.source_attribution` alongside `license_label` and
`license_url`. Surface those next to the download link on the front end —
distributing CC-BY material without credit breaches the licence.

## Not yet built

- **Multipart upload.** Single PUT is capped at 5 GB; renditions sit well under
  that, and `s3_put_file()` fails loudly rather than truncating if one ever
  exceeds it.
- **Front-end display** of licence and attribution on the download page.
- **TV support.** The queue has `season`/`episode` columns but the worker only
  handles movies.
