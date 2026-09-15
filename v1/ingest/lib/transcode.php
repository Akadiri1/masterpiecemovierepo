<?php
/**
 * ffmpeg / ffprobe wrappers for the publish worker.
 *
 * The ladder never upscales: a 480p source produces a 480p rendition only.
 * Inventing pixels would inflate storage and bandwidth while making the
 * download look worse than the honest original.
 */

/** Rendition definitions, smallest first. */
const FF_LADDER = [
    '480p'  => ['height' => 480,  'crf' => 21, 'maxrate' => '1400k', 'bufsize' => '2800k', 'audio' => '128k'],
    '720p'  => ['height' => 720,  'crf' => 21, 'maxrate' => '2800k', 'bufsize' => '5600k', 'audio' => '128k'],
    '1080p' => ['height' => 1080, 'crf' => 21, 'maxrate' => '5000k', 'bufsize' => '10000k', 'audio' => '192k'],
];

function ff_bin(string $which = 'ffmpeg'): string
{
    if ($which === 'ffprobe') {
        return defined('FFPROBE_BIN') && FFPROBE_BIN !== '' ? FFPROBE_BIN : 'ffprobe';
    }
    return defined('FFMPEG_BIN') && FFMPEG_BIN !== '' ? FFMPEG_BIN : 'ffmpeg';
}

/** Is the binary callable? Returns [ok, version-or-error]. */
function ff_check(string $which = 'ffmpeg'): array
{
    $bin = ff_bin($which);
    $out = [];
    $code = 0;
    @exec(escapeshellarg($bin) . ' -version 2>&1', $out, $code);

    if ($code !== 0 || empty($out)) {
        return [false, "'{$bin}' could not be executed"];
    }
    return [true, trim($out[0])];
}

/**
 * Probe a media file.
 *
 * @return array|null {width, height, duration, vcodec, acodec, bitrate}
 */
function ff_probe(string $path): ?array
{
    if (!is_readable($path)) {
        return null;
    }

    $cmd = escapeshellarg(ff_bin('ffprobe'))
        . ' -v quiet -print_format json -show_format -show_streams '
        . escapeshellarg($path);

    $json = @shell_exec($cmd);
    if (!$json) {
        return null;
    }

    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    $video = $audio = null;
    foreach ($data['streams'] ?? [] as $s) {
        if (($s['codec_type'] ?? '') === 'video' && $video === null) {
            $video = $s;
        }
        if (($s['codec_type'] ?? '') === 'audio' && $audio === null) {
            $audio = $s;
        }
    }

    if (!$video) {
        return null;
    }

    return [
        'width'    => (int) ($video['width'] ?? 0),
        'height'   => (int) ($video['height'] ?? 0),
        'duration' => (float) ($data['format']['duration'] ?? 0),
        'bitrate'  => (int) ($data['format']['bit_rate'] ?? 0),
        'vcodec'   => $video['codec_name'] ?? '?',
        'acodec'   => $audio['codec_name'] ?? null,
    ];
}

/**
 * Which renditions make sense for a source of this height.
 * Always returns at least one entry so a tiny source still gets a download.
 */
function ff_ladder_for(int $sourceHeight): array
{
    $out = [];
    foreach (FF_LADDER as $name => $spec) {
        // 8px tolerance: a 478p scan should still count as 480p.
        if ($sourceHeight >= $spec['height'] - 8) {
            $out[$name] = $spec;
        }
    }

    if (empty($out)) {
        // Source is smaller than our lowest rung: re-encode at native height
        // rather than upscaling it to 480p.
        $out['480p'] = ['height' => $sourceHeight, 'crf' => 21,
                        'maxrate' => '1000k', 'bufsize' => '2000k', 'audio' => '128k'];
    }

    return $out;
}

/**
 * Transcode one rendition. Streams ffmpeg's own progress to the console.
 *
 * @return array{ok:bool,error:?string,size:int}
 */
function ff_transcode(string $input, string $output, array $spec, bool $quiet = false): array
{
    $cmd = implode(' ', [
        escapeshellarg(ff_bin('ffmpeg')),
        '-y',
        '-i ' . escapeshellarg($input),
        '-vf ' . escapeshellarg('scale=-2:' . $spec['height']),
        '-c:v libx264',
        '-preset medium',
        '-crf ' . (int) $spec['crf'],
        '-maxrate ' . escapeshellarg($spec['maxrate']),
        '-bufsize ' . escapeshellarg($spec['bufsize']),
        '-c:a aac',
        '-b:a ' . escapeshellarg($spec['audio']),
        // faststart moves the index to the front so the file plays while
        // it is still downloading.
        '-movflags +faststart',
        $quiet ? '-loglevel error -nostats' : '-loglevel warning -stats',
        escapeshellarg($output),
        '2>&1',
    ]);

    $code = 0;
    passthru($cmd, $code);

    if ($code !== 0) {
        return ['ok' => false, 'error' => "ffmpeg exited with code {$code}", 'size' => 0];
    }
    if (!is_file($output) || filesize($output) === 0) {
        return ['ok' => false, 'error' => 'ffmpeg produced no output', 'size' => 0];
    }

    return ['ok' => true, 'error' => null, 'size' => (int) filesize($output)];
}

/** Human-readable byte size. */
function ff_human_size($bytes): string
{
    if (!$bytes) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = (int) floor(log($bytes, 1024));
    $i = max(0, min($i, count($units) - 1));
    return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
}

/** Format seconds as H:MM:SS. */
function ff_human_duration(float $seconds): string
{
    if ($seconds <= 0) return '?';
    return sprintf('%d:%02d:%02d', floor($seconds / 3600), floor(fmod($seconds, 3600) / 60), fmod($seconds, 60));
}
