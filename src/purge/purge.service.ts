import { Injectable } from '@nestjs/common';
import { WebhookRepository } from '../webhook/webhook.repository.js';

@Injectable()
export class PurgeService {
  static readonly DEFAULT_RETENTION_DAYS = 30;

  constructor(private readonly webhookRepository: WebhookRepository) {}

  async execute(days: number, now = new Date()): Promise<number> {
    const threshold = new Date(now);
    threshold.setDate(threshold.getDate() - days);
    return this.webhookRepository.deleteOlderThan(threshold);
  }
}
