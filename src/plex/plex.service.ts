import { Inject, Injectable, type OnModuleDestroy } from '@nestjs/common';
import { XMLParser } from 'fast-xml-parser';
import { Agent } from 'undici';
import { APP_CONFIG, type AppConfig } from '../config/app-config.js';
import type { Movie } from './movie.js';
import { integerValue, numberValue, stringValue } from './movie.js';

type XmlNode = Record<string, unknown>;

class PlexHttpError extends Error {
  constructor(readonly status: number) {
    super(`Plex returned HTTP ${status}`);
  }
}

@Injectable()
export class PlexService implements OnModuleDestroy {
  private readonly parser = new XMLParser({
    ignoreAttributes: false,
    attributeNamePrefix: '',
    parseAttributeValue: false,
  });
  // Mirrors Symfony HttpClient's legacy verify_host=false while keeping CA
  // chain validation enabled.
  private readonly dispatcher = new Agent({
    connect: { checkServerIdentity: () => undefined },
  });

  constructor(@Inject(APP_CONFIG) private readonly config: AppConfig) {}

  async onModuleDestroy(): Promise<void> {
    await this.dispatcher.close();
  }

  async checkConnection(): Promise<boolean> {
    try {
      await this.getXml('/');
      return true;
    } catch {
      return false;
    }
  }

  async getFromKey(ratingKey: string | number): Promise<Movie | null> {
    try {
      const document = await this.getXml(
        `/library/metadata/${encodeURIComponent(String(ratingKey))}`,
      );
      const container = this.asNode(document.MediaContainer);
      const video = this.firstNode(container?.Video);
      return video ? this.movieFromXml(video) : null;
    } catch (error) {
      if (error instanceof PlexHttpError && error.status >= 400 && error.status < 500) {
        return null;
      }
      throw error;
    }
  }

  private async getXml(endpoint: string): Promise<XmlNode> {
    if (this.config.plexServerUrl === '') {
      throw new Error('PLEX_SERVER_URL is not configured');
    }

    const baseUrl = new URL(this.config.plexServerUrl);
    if (baseUrl.port === '') baseUrl.port = '32400';
    const url = new URL(endpoint, baseUrl);
    url.searchParams.set('X-Plex-Token', this.config.plexToken);

    const response = await fetch(url, {
      method: 'GET',
      redirect: 'follow',
      signal: AbortSignal.timeout(10_000),
      dispatcher: this.dispatcher,
    } as RequestInit & { dispatcher: Agent });
    if (!response.ok) throw new PlexHttpError(response.status);

    const parsed: unknown = this.parser.parse(await response.text());
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
      throw new Error(`Invalid XML returned by Plex for ${endpoint}`);
    }

    return parsed as XmlNode;
  }

  private movieFromXml(video: XmlNode): Movie {
    const media = this.firstNode(video.Media) ?? {};

    return {
      ratingKey: stringValue(video.ratingKey),
      title: stringValue(video.title),
      summary: stringValue(video.summary),
      year: stringValue(video.year),
      duration: stringValue(video.duration),
      rating: stringValue(video.rating),
      thumb: stringValue(video.thumb),
      studio: stringValue(video.studio),
      contentRating: stringValue(video.contentRating),
      genres: this.tagValues(video.Genre),
      directors: this.tagValues(video.Director),
      writers: this.tagValues(video.Writer),
      actors: this.tagValues(video.Role),
      countries: this.tagValues(video.Country),
      aspectRatio: numberValue(media.aspectRatio),
      bitrate: integerValue(media.bitrate),
      width: integerValue(media.width),
      height: integerValue(media.height),
      audioCodec: stringValue(media.audioCodec),
      videoCodec: stringValue(media.videoCodec),
      videoResolution: stringValue(media.videoResolution),
      container: stringValue(media.container),
      frameRate: stringValue(media.videoFrameRate),
    };
  }

  private tagValues(value: unknown): string[] {
    const nodes = Array.isArray(value) ? value : value ? [value] : [];
    return nodes.flatMap((node) => {
      const tag = stringValue(this.asNode(node)?.tag);
      return tag === '' ? [] : [tag];
    });
  }

  private firstNode(value: unknown): XmlNode | null {
    const candidate = Array.isArray(value) ? value[0] : value;
    return this.asNode(candidate);
  }

  private asNode(value: unknown): XmlNode | null {
    return value && typeof value === 'object' && !Array.isArray(value)
      ? (value as XmlNode)
      : null;
  }
}
