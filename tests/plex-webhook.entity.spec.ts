import { describe, expect, it } from 'vitest';
import { PlexWebhookEntity } from '../src/webhook/plex-webhook.entity.js';

describe('PlexWebhookEntity', () => {
  it('returns empty metadata and no guid when Metadata is absent', () => {
    const webhook = new PlexWebhookEntity({
      content: { event: 'device.new' },
      type: 'device.new',
    });

    expect(webhook.getMetadata()).toEqual({});
    expect(webhook.getGuid()).toBeNull();
  });

  it('maps and casts webhook metadata to the legacy Movie shape', () => {
    const webhook = new PlexWebhookEntity({
      type: 'library.new',
      content: {
        event: 'library.new',
        Metadata: {
          guid: 'plex://movie/abc',
          ratingKey: 12345,
          title: 'Blade Runner',
          year: 1982,
          rating: 8.1,
          width: 1920,
          height: 1080,
          Genre: [{ tag: 'Sci-Fi' }, { tag: 'Thriller' }],
          Director: [{ tag: 'Ridley Scott' }],
          Role: [{ tag: 'Harrison Ford' }],
        },
      },
    });

    expect(webhook.getGuid()).toBe('plex://movie/abc');
    expect(webhook.asMovie()).toMatchObject({
      ratingKey: '12345',
      title: 'Blade Runner',
      year: '1982',
      rating: '8.1',
      width: 1920,
      height: 1080,
      genres: ['Sci-Fi', 'Thriller'],
      directors: ['Ridley Scott'],
      actors: ['Harrison Ford'],
    });
  });

  it('tolerates missing optional movie fields', () => {
    const webhook = new PlexWebhookEntity({
      type: 'library.new',
      content: { event: 'library.new', Metadata: { title: 'Untitled' } },
    });

    expect(webhook.asMovie()).toMatchObject({
      ratingKey: '',
      year: '',
      width: 0,
      genres: [],
    });
  });
});
