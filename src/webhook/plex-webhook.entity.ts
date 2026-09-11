import type { Movie } from '../plex/movie.js';
import {
  integerValue,
  numberValue,
  stringValue,
  tags,
} from '../plex/movie.js';

export type WebhookContent = Record<string, unknown>;

export interface PlexWebhookValues {
  id?: number;
  content: WebhookContent;
  type: string;
  createdAt?: Date;
  thumb?: Uint8Array | null;
}

export class PlexWebhookEntity {
  readonly id?: number;
  readonly content: WebhookContent;
  readonly type: string;
  readonly createdAt: Date;
  readonly thumb: Buffer | null;

  constructor(values: PlexWebhookValues) {
    this.id = values.id;
    this.content = values.content;
    this.type = values.type;
    this.createdAt = values.createdAt ?? new Date();
    this.thumb = values.thumb ? Buffer.from(values.thumb) : null;
  }

  getMetadata(): WebhookContent {
    const metadata = this.content.Metadata;
    return metadata && typeof metadata === 'object' && !Array.isArray(metadata)
      ? (metadata as WebhookContent)
      : {};
  }

  getGuid(): string | null {
    const guid = this.getMetadata().guid;
    return typeof guid === 'string' ? guid : null;
  }

  asMovie(): Movie {
    const metadata = this.getMetadata();

    return {
      ratingKey: stringValue(metadata.ratingKey),
      title: stringValue(metadata.title),
      summary: stringValue(metadata.summary),
      year: stringValue(metadata.year),
      duration: stringValue(metadata.duration),
      rating: stringValue(metadata.rating ?? 0),
      thumb: stringValue(metadata.thumb),
      studio: stringValue(metadata.studio),
      contentRating: stringValue(metadata.contentRating),
      genres: tags(metadata.Genre),
      directors: tags(metadata.Director),
      writers: tags(metadata.Writer),
      actors: tags(metadata.Role),
      countries: tags(metadata.Country),
      aspectRatio: numberValue(metadata.aspectRatio),
      bitrate: integerValue(metadata.bitrate),
      width: integerValue(metadata.width),
      height: integerValue(metadata.height),
      audioCodec: stringValue(metadata.audioCodec),
      videoCodec: stringValue(metadata.videoCodec),
      videoResolution: stringValue(metadata.videoResolution),
      container: stringValue(metadata.container),
      frameRate: stringValue(metadata.frameRate),
    };
  }
}
