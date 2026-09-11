import { Inject, Injectable } from '@nestjs/common';
import nodemailer, { type Transporter } from 'nodemailer';
import { APP_CONFIG, type AppConfig } from '../config/app-config.js';
import type { DigestMovie } from './digest-template.service.js';
import { DigestTemplateService } from './digest-template.service.js';

@Injectable()
export class MailerService {
  private transport?: Transporter;

  constructor(
    @Inject(APP_CONFIG) private readonly config: AppConfig,
    private readonly template: DigestTemplateService,
  ) {}

  async sendDigest(movies: DigestMovie[]): Promise<void> {
    const attachments = movies.flatMap(({ movie, thumb }) =>
      thumb
        ? [
            {
              filename: movie.ratingKey,
              content: thumb,
              contentType: 'image/jpeg',
              cid: movie.ratingKey,
              contentDisposition: 'inline' as const,
            },
          ]
        : [],
    );

    await this.getTransport().sendMail({
      from: { address: this.config.mailSender, name: 'David' },
      to: this.config.destinationEmails,
      subject: 'Les films de la semaine sur DadaNAS',
      html: this.template.render(movies),
      attachments,
    });
  }

  private getTransport(): Transporter {
    if (this.transport) return this.transport;

    if (this.config.mailerDsn === 'null://null') {
      this.transport = nodemailer.createTransport({ jsonTransport: true });
    } else if (this.config.mailerDsn === 'sendmail://default') {
      this.transport = nodemailer.createTransport({ sendmail: true });
    } else {
      this.transport = nodemailer.createTransport(this.config.mailerDsn);
    }

    return this.transport;
  }
}
