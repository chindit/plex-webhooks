import { Global, Module } from '@nestjs/common';

export const APP_CONFIG = Symbol('APP_CONFIG');

export interface AppConfig {
  databaseUrl: string;
  destinationEmails: string[];
  mailerDsn: string;
  mailSender: string;
  plexServerUrl: string;
  plexToken: string;
  port: number;
  webhookToken: string;
}

export function loadAppConfig(): AppConfig {
  const port = Number.parseInt(process.env.PORT ?? '3000', 10);

  return {
    databaseUrl: process.env.DATABASE_URL ?? '',
    destinationEmails: (process.env.DESTINATION_EMAIL ?? '')
      .split(',')
      .map((email) => email.trim())
      .filter(Boolean),
    mailerDsn: process.env.MAILER_DSN ?? '',
    mailSender: process.env.MAIL_SENDER ?? '',
    plexServerUrl: process.env.PLEX_SERVER_URL ?? '',
    plexToken: process.env.PLEX_TOKEN ?? '',
    port: Number.isInteger(port) && port > 0 && port <= 65_535 ? port : 3000,
    webhookToken: process.env.WEBHOOK_TOKEN ?? '',
  };
}

@Global()
@Module({
  providers: [{ provide: APP_CONFIG, useFactory: loadAppConfig }],
  exports: [APP_CONFIG],
})
export class AppConfigModule {}
