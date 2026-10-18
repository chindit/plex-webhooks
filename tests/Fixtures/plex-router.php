<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
file_put_contents(getenv('PLEX_TEST_REQUEST_LOG'), $path . "\n", FILE_APPEND);

if (($_GET['X-Plex-Token'] ?? null) !== 'test-token') {
    http_response_code(401);
    return;
}

header('Content-Type: application/xml');
if ($path === '/identity') {
    echo '<MediaContainer machineIdentifier="test-server" version="1.0" />';
} elseif (preg_match('~^/library/metadata/(10|11)$~', $path, $matches)) {
    echo '<MediaContainer><Video ratingKey="' . $matches[1] . '" title="Plex movie" summary="Plex summary" year="2026" duration="3900000">'
        . '<Media bitrate="5000" width="1920" height="1080" aspectRatio="1.78" audioChannels="2" audioCodec="aac" videoCodec="h264" videoResolution="1080" container="mkv" videoFrameRate="24" videoProfile="high" />'
        . '<Genre tag="Drama" /><Role tag="An Actor" />'
        . '</Video></MediaContainer>';
} else {
    http_response_code($path === '/library/metadata/500' ? 503 : 404);
}
