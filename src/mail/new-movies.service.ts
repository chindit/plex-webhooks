import { Injectable } from '@nestjs/common';
import { PlexService } from '../plex/plex.service.js';
import { WebhookRepository } from '../webhook/webhook.repository.js';
import type { DigestMovie } from './digest-template.service.js';
import { MailerService } from './mailer.service.js';

export type NewMoviesResult =
  | { status: 'empty'; count: 0 }
  | { status: 'sent'; count: number };

@Injectable()
export class NewMoviesService {
  constructor(
    private readonly webhookRepository: WebhookRepository,
    private readonly plexServer: PlexService,
    private readonly mailer: MailerService,
  ) {}

  async execute(now = new Date()): Promise<NewMoviesResult> {
    const webhooks = await this.webhookRepository.findNewMoviesFromLastWeek(now);
    const canConnectToPlex = await this.plexServer.checkConnection();
    const moviesByRatingKey = new Map<string, DigestMovie>();

    for (const webhook of webhooks) {
      const guid = webhook.getGuid();
      if (guid === null) continue;

      let movie = null;
      if (guid.startsWith('local')) {
        if (canConnectToPlex) {
          const ratingKey = webhook.getMetadata().ratingKey;
          if (typeof ratingKey === 'string' || typeof ratingKey === 'number') {
            movie = await this.plexServer.getFromKey(ratingKey);
          }
        }
      } else {
        movie = webhook.asMovie();
      }

      if (movie) {
        moviesByRatingKey.set(movie.ratingKey, { movie, thumb: webhook.thumb });
      }
    }

    if (moviesByRatingKey.size === 0) return { status: 'empty', count: 0 };

    await this.mailer.sendDigest([...moviesByRatingKey.values()]);
    return { status: 'sent', count: moviesByRatingKey.size };
  }
}
