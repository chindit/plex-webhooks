import { Module } from '@nestjs/common';
import { WebhookController } from './webhook.controller.js';
import { WebhookRepository } from './webhook.repository.js';

@Module({
  controllers: [WebhookController],
  providers: [WebhookRepository],
  exports: [WebhookRepository],
})
export class WebhookModule {}
