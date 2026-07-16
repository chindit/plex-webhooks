<?php

namespace App\Enum;

/**
 * Known Plex webhook event types.
 *
 * @see https://support.plex.tv/articles/115002267687-webhooks/
 */
enum PlexEventType: string
{
    case LibraryOnDeck = 'library.on.deck';
    case LibraryNew = 'library.new';
    case MediaPause = 'media.pause';
    case MediaPlay = 'media.play';
    case MediaRate = 'media.rate';
    case MediaResume = 'media.resume';
    case MediaScrobble = 'media.scrobble';
    case MediaStop = 'media.stop';
    case AdminDatabaseBackup = 'admin.database.backup';
    case AdminDatabaseCorrupted = 'admin.database.corrupted';
    case DeviceNew = 'device.new';
    case PlaybackStarted = 'playback.started';
}
