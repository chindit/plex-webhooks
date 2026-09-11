import { Module } from '@nestjs/common';
import { PlexModule } from '../plex/plex.module.js';
import { WebhookModule } from '../webhook/webhook.module.js';
import { DigestTemplateService } from './digest-template.service.js';
import { MailerService } from './mailer.service.js';
import { NewMoviesService } from './new-movies.service.js';

@Module({
  imports: [PlexModule, WebhookModule],
  providers: [DigestTemplateService, MailerService, NewMoviesService],
  exports: [NewMoviesService],
})
export class MailModule {}
