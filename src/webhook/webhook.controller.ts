import {
  All,
  Controller,
  HttpStatus,
  Inject,
  Post,
  Res,
  UploadedFile,
  UseInterceptors,
} from '@nestjs/common';
import { FileInterceptor } from '@nestjs/platform-express';
import { createHash, timingSafeEqual } from 'node:crypto';
import type { Response } from 'express';
import { fileTypeFromBuffer } from 'file-type';
import { APP_CONFIG, type AppConfig } from '../config/app-config.js';
import { WebhookRepository } from './webhook.repository.js';

const MAX_THUMB_SIZE = 2 * 1024 * 1024;

interface UploadedThumb {
  buffer: Buffer;
}

@Controller()
export class WebhookController {
  constructor(
    @Inject(APP_CONFIG) private readonly config: AppConfig,
    private readonly repository: WebhookRepository,
  ) {}

  @Post('/')
  @UseInterceptors(FileInterceptor('thumb'))
  async receive(
    @Res() response: Response,
    @UploadedFile() thumb: UploadedThumb | undefined,
  ): Promise<void> {
    const request = response.req;
    const token = typeof request.query.token === 'string' ? request.query.token : '';

    if (!this.isAuthorized(token)) {
      response.status(HttpStatus.UNAUTHORIZED).json(null);
      return;
    }

    const payload = request.body?.payload;
    if (typeof payload !== 'string' || payload === '') {
      response.status(HttpStatus.BAD_REQUEST).json(null);
      return;
    }

    let content: unknown;
    try {
      content = JSON.parse(payload);
    } catch {
      response.status(HttpStatus.BAD_REQUEST).json(null);
      return;
    }

    if (!content || typeof content !== 'object' || Array.isArray(content)) {
      response.status(HttpStatus.BAD_REQUEST).json(null);
      return;
    }

    const event = (content as Record<string, unknown>).event;
    if (event === null || event === undefined) {
      response.status(HttpStatus.BAD_REQUEST).json(null);
      return;
    }

    if (!['string', 'number', 'boolean'].includes(typeof event)) {
      response.status(HttpStatus.BAD_REQUEST).json(null);
      return;
    }

    const acceptedThumb = thumb && (await this.isAcceptableThumb(thumb.buffer))
      ? thumb.buffer
      : null;

    await this.repository.save(
      content as Record<string, unknown>,
      String(event),
      acceptedThumb,
    );

    response.status(HttpStatus.ACCEPTED).json(null);
  }

  @All('/')
  methodNotAllowed(@Res() response: Response): void {
    response.setHeader('Allow', 'POST');
    response.status(HttpStatus.METHOD_NOT_ALLOWED).json(null);
  }

  private isAuthorized(receivedToken: string): boolean {
    if (this.config.webhookToken === '') return false;

    const expected = createHash('sha256').update(this.config.webhookToken).digest();
    const received = createHash('sha256').update(receivedToken).digest();
    return timingSafeEqual(expected, received);
  }

  private async isAcceptableThumb(buffer: Buffer): Promise<boolean> {
    if (buffer.length > MAX_THUMB_SIZE) return false;

    const detected = await fileTypeFromBuffer(buffer);
    if (detected?.mime.startsWith('image/')) return true;

    const beginning = buffer.subarray(0, 512).toString('utf8').trimStart();
    return /^<\?xml[\s\S]*?<svg\b|^<svg\b/i.test(beginning);
  }
}
