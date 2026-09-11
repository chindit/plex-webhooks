import { afterEach, describe, expect, it, vi } from 'vitest';
import type { AppConfig } from '../src/config/app-config.js';
import { PlexService } from '../src/plex/plex.service.js';

const config: AppConfig = {
  databaseUrl: '',
  destinationEmails: [],
  mailerDsn: '',
  mailSender: '',
  plexServerUrl: 'http://plex.local',
  plexToken: 'plex-secret',
  port: 3000,
  webhookToken: '',
};

describe('PlexService', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('checks connectivity using the legacy port and query token', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response('<MediaContainer size="0"></MediaContainer>', { status: 200 }),
    );
    vi.stubGlobal('fetch', fetchMock);

    await expect(new PlexService(config).checkConnection()).resolves.toBe(true);
    const url = fetchMock.mock.calls[0]?.[0] as URL;
    expect(url.origin).toBe('http://plex.local:32400');
    expect(url.searchParams.get('X-Plex-Token')).toBe('plex-secret');
  });

  it('maps Plex XML metadata and technical fields', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        new Response(
          `<MediaContainer size="1"><Video ratingKey="7" title="Arrival" summary="First contact" year="2016" duration="6960000" rating="7.9"><Genre tag="Drama"/><Genre tag="Sci-Fi"/><Director tag="Denis Villeneuve"/><Role tag="Amy Adams"/><Media bitrate="12000" width="3840" height="2160" aspectRatio="1.78" audioCodec="dca" videoCodec="hevc" videoResolution="4k" container="mkv" videoFrameRate="24p"/></Video></MediaContainer>`,
          { status: 200 },
        ),
      ),
    );

    await expect(new PlexService(config).getFromKey('7')).resolves.toMatchObject({
      ratingKey: '7',
      title: 'Arrival',
      duration: '6960000',
      genres: ['Drama', 'Sci-Fi'],
      directors: ['Denis Villeneuve'],
      actors: ['Amy Adams'],
      bitrate: 12000,
      width: 3840,
      height: 2160,
      videoResolution: '4k',
    });
  });

  it('returns null on a missing Plex item but reports connectivity failures', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('', { status: 404 })));
    await expect(new PlexService(config).getFromKey('missing')).resolves.toBeNull();

    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
    await expect(new PlexService(config).checkConnection()).resolves.toBe(false);
  });
});
