import { Module } from '@nestjs/common';
import { WebhookModule } from '../webhook/webhook.module.js';
import { PurgeService } from './purge.service.js';

@Module({
  imports: [WebhookModule],
  providers: [PurgeService],
  exports: [PurgeService],
})
export class PurgeModule {}
