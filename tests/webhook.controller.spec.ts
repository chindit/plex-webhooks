import type { INestApplication } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import request from 'supertest';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { APP_CONFIG, type AppConfig } from '../src/config/app-config.js';
import { WebhookController } from '../src/webhook/webhook.controller.js';
import { WebhookRepository } from '../src/webhook/webhook.repository.js';

describe('WebhookController', () => {
  let app: INestApplication;
  const save = vi.fn();
  const config: AppConfig = {
    databaseUrl: '',
    destinationEmails: [],
    mailerDsn: 'null://null',
    mailSender: '',
    plexServerUrl: '',
    plexToken: '',
    port: 3000,
    webhookToken: 'test-secret-token',
  };

  beforeEach(async () => {
    save.mockReset().mockResolvedValue(undefined);
    const module = await Test.createTestingModule({
      controllers: [WebhookController],
      providers: [
        { provide: APP_CONFIG, useValue: config },
        { provide: WebhookRepository, useValue: { save } },
      ],
    }).compile();
    app = module.createNestApplication();
    await app.init();
  });

  afterEach(async () => app.close());

  it('rejects a missing or incorrect token with the legacy JSON null body', async () => {
    await request(app.getHttpServer())
      .post('/')
      .field('payload', '{"event":"library.new"}')
      .expect(401)
      .expect('null');
    await request(app.getHttpServer())
      .post('/?token=wrong')
      .field('payload', '{"event":"library.new"}')
      .expect(401)
      .expect('null');
    expect(save).not.toHaveBeenCalled();
  });

  it('rejects missing, invalid, or event-less payloads', async () => {
    await request(app.getHttpServer()).post('/?token=test-secret-token').expect(400);
    await request(app.getHttpServer())
      .post('/?token=test-secret-token')
      .field('payload', 'not-json')
      .expect(400);
    await request(app.getHttpServer())
      .post('/?token=test-secret-token')
      .field('payload', '{}')
      .expect(400);
    expect(save).not.toHaveBeenCalled();
  });

  it('accepts a Plex multipart payload and stores a valid image', async () => {
    const png = Buffer.from(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
      'base64',
    );

    await request(app.getHttpServer())
      .post('/?token=test-secret-token')
      .field('payload', '{"event":"library.new","Metadata":{"title":"Alien"}}')
      .attach('thumb', png, { filename: 'thumb.png', contentType: 'text/plain' })
      .expect(202)
      .expect('null');

    expect(save).toHaveBeenCalledWith(
      { event: 'library.new', Metadata: { title: 'Alien' } },
      'library.new',
      png,
    );
  });

  it('keeps accepting application/x-www-form-urlencoded payloads without a thumb', async () => {
    await request(app.getHttpServer())
      .post('/?token=test-secret-token')
      .type('form')
      .send({ payload: '{"event":"device.new"}' })
      .expect(202)
      .expect('null');

    expect(save).toHaveBeenCalledWith({ event: 'device.new' }, 'device.new', null);
  });

  it('ignores a non-image thumbnail but still stores the webhook', async () => {
    await request(app.getHttpServer())
      .post('/?token=test-secret-token')
      .field('payload', '{"event":"library.new"}')
      .attach('thumb', Buffer.from('not an image'), 'thumb.txt')
      .expect(202);

    expect(save).toHaveBeenCalledWith({ event: 'library.new' }, 'library.new', null);
  });

  it('ignores an image larger than 2 MiB but still stores the webhook', async () => {
    const oversized = Buffer.alloc(2 * 1024 * 1024 + 1);
    Buffer.from('89504e470d0a1a0a', 'hex').copy(oversized);

    await request(app.getHttpServer())
      .post('/?token=test-secret-token')
      .field('payload', '{"event":"library.new"}')
      .attach('thumb', oversized, 'thumb.png')
      .expect(202);

    expect(save).toHaveBeenCalledWith({ event: 'library.new' }, 'library.new', null);
  });

  it('returns 405 for methods other than POST', async () => {
    await request(app.getHttpServer())
      .get('/?token=test-secret-token')
      .expect('Allow', 'POST')
      .expect(405)
      .expect('null');
  });
});
