import { Module } from '@nestjs/common';
import { PlexService } from './plex.service.js';

@Module({
  providers: [PlexService],
  exports: [PlexService],
})
export class PlexModule {}
