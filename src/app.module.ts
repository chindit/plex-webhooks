import { Module } from '@nestjs/common';
import { AppConfigModule } from './config/app-config.js';
import { MailModule } from './mail/mail.module.js';
import { PrismaModule } from './prisma/prisma.module.js';
import { PurgeModule } from './purge/purge.module.js';
import { WebhookModule } from './webhook/webhook.module.js';

@Module({
  imports: [AppConfigModule, PrismaModule, WebhookModule, MailModule, PurgeModule],
})
export class AppModule {}
