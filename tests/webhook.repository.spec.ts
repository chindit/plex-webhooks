import { describe, expect, it, vi } from 'vitest';
import type { PrismaService } from '../src/prisma/prisma.service.js';
import { PlexEventType } from '../src/webhook/plex-event-type.js';
import { WebhookRepository } from '../src/webhook/webhook.repository.js';

describe('WebhookRepository', () => {
  it('stores the creation time in the application like the Doctrine entity did', async () => {
    const create = vi.fn().mockResolvedValue({});
    const prisma = { plexWebhook: { create } } as unknown as PrismaService;
    const repository = new WebhookRepository(prisma);
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-09-11T12:34:56.000Z'));

    try {
      await repository.save({ event: 'library.new' }, 'library.new', Buffer.from('x'));
    } finally {
      vi.useRealTimers();
    }

    expect(create).toHaveBeenCalledWith({
      data: {
        content: { event: 'library.new' },
        type: 'library.new',
        createdAt: new Date('2026-09-11T12:34:56.000Z'),
        thumb: Uint8Array.from(Buffer.from('x')),
      },
    });
  });

  it('uses strict weekly and purge boundaries with the legacy event type', async () => {
    const findMany = vi.fn().mockResolvedValue([]);
    const deleteMany = vi.fn().mockResolvedValue({ count: 4 });
    const prisma = {
      plexWebhook: { findMany, deleteMany },
    } as unknown as PrismaService;
    const repository = new WebhookRepository(prisma);
    const now = new Date('2026-09-11T12:00:00.000Z');

    await repository.findNewMoviesFromLastWeek(now);
    await expect(repository.deleteOlderThan(now)).resolves.toBe(4);

    expect(findMany).toHaveBeenCalledWith({
      where: {
        createdAt: { gt: new Date('2026-09-04T12:00:00.000Z') },
        type: PlexEventType.LibraryNew,
      },
    });
    expect(deleteMany).toHaveBeenCalledWith({
      where: { createdAt: { lt: now } },
    });
  });
});
