export interface Movie {
  ratingKey: string;
  title: string;
  summary: string;
  year: string;
  duration: string;
  rating: string;
  thumb: string;
  studio: string;
  contentRating: string;
  genres: string[];
  directors: string[];
  writers: string[];
  actors: string[];
  countries: string[];
  aspectRatio: number;
  bitrate: number;
  width: number;
  height: number;
  audioCodec: string;
  videoCodec: string;
  videoResolution: string;
  container: string;
  frameRate: string;
}

export function stringValue(value: unknown): string {
  if (value === null || value === undefined) return '';
  if (typeof value === 'string') return value;
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);
  return '';
}

export function numberValue(value: unknown): number {
  if (typeof value === 'number') return Number.isFinite(value) ? value : 0;
  if (typeof value === 'string') {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
  }
  return 0;
}

export function integerValue(value: unknown): number {
  return Math.trunc(numberValue(value));
}

export function tags(value: unknown): string[] {
  if (!Array.isArray(value)) return [];

  return value.flatMap((item) => {
    if (!item || typeof item !== 'object' || !('tag' in item)) return [];
    const tag = stringValue((item as { tag: unknown }).tag);
    return tag === '' ? [] : [tag];
  });
}
