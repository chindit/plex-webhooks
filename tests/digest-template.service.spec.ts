import { describe, expect, it } from 'vitest';
import type { Movie } from '../src/plex/movie.js';
import {
  DigestTemplateService,
  formatDuration,
} from '../src/mail/digest-template.service.js';

const movie: Movie = {
  ratingKey: '42',
  title: '<Alien>',
  summary: 'Dans l’espace & ailleurs',
  year: '1979',
  duration: '7025000',
  rating: '8.5',
  thumb: '',
  studio: '',
  contentRating: '',
  genres: ['Science-fiction', 'Horreur'],
  directors: ['Ridley Scott'],
  writers: [],
  actors: ['A', 'B', 'C', 'D', 'E', 'F'],
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

describe('DigestTemplateService', () => {
  it('preserves the legacy UTC duration rendering', () => {
    expect(formatDuration(7_025_000)).toBe('01:57:05');
    expect(formatDuration(90_000_000)).toBe('01:00:00');
  });

  it('renders escaped content, the CID, genres, and only five actors', () => {
    const html = new DigestTemplateService().render([{ movie, thumb: null }]);
    expect(html).toContain('Films de la semaine');
    expect(html).toContain('cid:42');
    expect(html).toContain('&lt;Alien&gt;');
    expect(html).toContain('Science-fiction, Horreur');
    expect(html).toContain('A, B, C, D, E');
    expect(html).not.toContain('A, B, C, D, E, F');
    expect(html).toContain('style="font-weight: bold;');
  });
});
