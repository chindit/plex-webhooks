import { Injectable } from '@nestjs/common';
import type { Prisma } from '../generated/prisma/client.js';
import { PrismaService } from '../prisma/prisma.service.js';
import { PlexEventType } from './plex-event-type.js';
import {
  PlexWebhookEntity,
  type WebhookContent,
} from './plex-webhook.entity.js';

@Injectable()
export class WebhookRepository {
  constructor(private readonly prisma: PrismaService) {}

  async save(content: WebhookContent, type: string, thumb: Buffer | null): Promise<void> {
    await this.prisma.plexWebhook.create({
      data: {
        content: content as Prisma.InputJsonValue,
        type,
        createdAt: new Date(),
        thumb: thumb ? new Uint8Array(thumb) : null,
      },
    });
  }

  async findNewMoviesFromLastWeek(now = new Date()): Promise<PlexWebhookEntity[]> {
    const lastWeek = new Date(now);
    lastWeek.setDate(lastWeek.getDate() - 7);

    const records = await this.prisma.plexWebhook.findMany({
      where: {
        createdAt: { gt: lastWeek },
        type: PlexEventType.LibraryNew,
      },
    });

    return records.map(
      (record) =>
        new PlexWebhookEntity({
          ...record,
          content: record.content as WebhookContent,
        }),
    );
  }

  deleteOlderThan(threshold: Date): Promise<number> {
    return this.prisma.plexWebhook
      .deleteMany({ where: { createdAt: { lt: threshold } } })
      .then(({ count }) => count);
  }
}
