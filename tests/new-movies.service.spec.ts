import { describe, expect, it, vi } from 'vitest';
import { NewMoviesService } from '../src/mail/new-movies.service.js';
import type { MailerService } from '../src/mail/mailer.service.js';
import type { Movie } from '../src/plex/movie.js';
import type { PlexService } from '../src/plex/plex.service.js';
import { PlexWebhookEntity } from '../src/webhook/plex-webhook.entity.js';
import type { WebhookRepository } from '../src/webhook/webhook.repository.js';

function movie(ratingKey: string, title: string): Movie {
  return {
    ratingKey,
    title,
    summary: '',
    year: '',
    duration: '',
    rating: '',
    thumb: '',
    studio: '',
    contentRating: '',
    genres: [],
    directors: [],
    writers: [],
    actors: [],
    countries: [],
    aspectRatio: 0,
    bitrate: 0,
    width: 0,
    height: 0,
    audioCodec: '',
    videoCodec: '',
    videoResolution: '',
    container: '',
    frameRate: '',
  };
}

describe('NewMoviesService', () => {
  it('resolves local GUIDs, maps remote GUIDs, deduplicates by rating key, and mails', async () => {
    const firstThumb = Buffer.from('first');
    const replacementThumb = Buffer.from('replacement');
    const webhooks = [
      new PlexWebhookEntity({
        type: 'library.new',
        thumb: firstThumb,
        content: {
          Metadata: { guid: 'plex://movie/a', ratingKey: '1', title: 'First' },
        },
      }),
      new PlexWebhookEntity({
        type: 'library.new',
        thumb: replacementThumb,
        content: {
          Metadata: { guid: 'plex://movie/a-copy', ratingKey: '1', title: 'Replacement' },
        },
      }),
      new PlexWebhookEntity({
        type: 'library.new',
        content: {
          Metadata: { guid: 'local://movie/b', ratingKey: 2, title: 'Local' },
        },
      }),
      new PlexWebhookEntity({
        type: 'library.new',
        content: { Metadata: { title: 'No guid' } },
      }),
    ];
    const repository = {
      findNewMoviesFromLastWeek: vi.fn().mockResolvedValue(webhooks),
    } as unknown as WebhookRepository;
    const plex = {
      checkConnection: vi.fn().mockResolvedValue(true),
      getFromKey: vi.fn().mockResolvedValue(movie('2', 'Resolved Local')),
    } as unknown as PlexService;
    const mailer = {
      sendDigest: vi.fn().mockResolvedValue(undefined),
    } as unknown as MailerService;

    const result = await new NewMoviesService(repository, plex, mailer).execute(
      new Date('2026-09-11T10:00:00Z'),
    );

    expect(result).toEqual({ status: 'sent', count: 2 });
    expect(plex.getFromKey).toHaveBeenCalledWith(2);
    expect(mailer.sendDigest).toHaveBeenCalledWith([
      expect.objectContaining({
        movie: expect.objectContaining({ title: 'Replacement', ratingKey: '1' }),
        thumb: replacementThumb,
      }),
      expect.objectContaining({
        movie: expect.objectContaining({ title: 'Resolved Local', ratingKey: '2' }),
        thumb: null,
      }),
    ]);
  });

  it('does not query local items while Plex is unavailable and sends no empty mail', async () => {
    const repository = {
      findNewMoviesFromLastWeek: vi.fn().mockResolvedValue([
        new PlexWebhookEntity({
          type: 'library.new',
          content: { Metadata: { guid: 'local://movie/a', ratingKey: '1' } },
        }),
      ]),
    } as unknown as WebhookRepository;
    const plex = {
      checkConnection: vi.fn().mockResolvedValue(false),
      getFromKey: vi.fn(),
    } as unknown as PlexService;
    const mailer = { sendDigest: vi.fn() } as unknown as MailerService;

    const result = await new NewMoviesService(repository, plex, mailer).execute();

    expect(result).toEqual({ status: 'empty', count: 0 });
    expect(plex.getFromKey).not.toHaveBeenCalled();
    expect(mailer.sendDigest).not.toHaveBeenCalled();
  });
});
