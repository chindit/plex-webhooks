import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { AppConfig } from '../src/config/app-config.js';
import { DigestTemplateService } from '../src/mail/digest-template.service.js';
import { MailerService } from '../src/mail/mailer.service.js';
import type { Movie } from '../src/plex/movie.js';

const mocks = vi.hoisted(() => ({
  createTransport: vi.fn(),
  sendMail: vi.fn(),
}));

vi.mock('nodemailer', () => ({
  default: { createTransport: mocks.createTransport },
}));

const config: AppConfig = {
  databaseUrl: '',
  destinationEmails: ['one@example.test', 'two@example.test'],
  mailerDsn: 'smtp://mail.example.test',
  mailSender: 'plex@example.test',
  plexServerUrl: '',
  plexToken: '',
  port: 3000,
  webhookToken: '',
};

const movie: Movie = {
  ratingKey: '99',
  title: 'Alien',
  summary: '',
  year: '1979',
  duration: '1000',
  rating: '',
  thumb: '',
  studio: '',
  contentRating: '',
  genres: [],
  directors: [],
  writers: [],
  actors: [],
  countries: [],
  aspectRatio: 0,
  bitrate: 0,
  width: 0,
  height: 0,
  audioCodec: '',
  videoCodec: '',
  videoResolution: '',
  container: '',
  frameRate: '',
};

describe('MailerService', () => {
  beforeEach(() => {
    mocks.sendMail.mockReset().mockResolvedValue(undefined);
    mocks.createTransport.mockReset().mockReturnValue({ sendMail: mocks.sendMail });
  });

  it('preserves sender, recipients, subject, and inline JPEG attachments', async () => {
    const thumb = Buffer.from('poster');
    const mailer = new MailerService(config, new DigestTemplateService());

    await mailer.sendDigest([{ movie, thumb }]);

    expect(mocks.createTransport).toHaveBeenCalledWith('smtp://mail.example.test');
    expect(mocks.sendMail).toHaveBeenCalledWith(
      expect.objectContaining({
        from: { address: 'plex@example.test', name: 'David' },
        to: ['one@example.test', 'two@example.test'],
        subject: 'Les films de la semaine sur DadaNAS',
        attachments: [
          {
            filename: '99',
            content: thumb,
            contentType: 'image/jpeg',
            cid: '99',
            contentDisposition: 'inline',
          },
        ],
      }),
    );
  });
});
