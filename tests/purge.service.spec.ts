import { describe, expect, it, vi } from 'vitest';
import { PurgeService } from '../src/purge/purge.service.js';
import type { WebhookRepository } from '../src/webhook/webhook.repository.js';

describe('PurgeService', () => {
  it('deletes entries older than the requested number of calendar days', async () => {
    const deleteOlderThan = vi.fn().mockResolvedValue(3);
    const service = new PurgeService({ deleteOlderThan } as unknown as WebhookRepository);

    await expect(
      service.execute(30, new Date('2026-09-11T12:00:00.000Z')),
    ).resolves.toBe(3);
    expect(deleteOlderThan).toHaveBeenCalledWith(new Date('2026-08-12T12:00:00.000Z'));
  });
});
