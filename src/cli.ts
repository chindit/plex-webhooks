import 'reflect-metadata';
import 'dotenv/config';
import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module.js';
import { NewMoviesService } from './mail/new-movies.service.js';
import { PurgeService } from './purge/purge.service.js';

function daysOption(args: string[]): number {
  let raw = String(PurgeService.DEFAULT_RETENTION_DAYS);
  for (let index = 0; index < args.length; index += 1) {
    const argument = args[index];
    if (argument === '--days' || argument === '-d') raw = args[index + 1] ?? '';
    if (argument.startsWith('--days=')) raw = argument.slice('--days='.length);
    if (argument.startsWith('-d') && argument.length > 2) raw = argument.slice(2);
  }
  return Number.parseInt(raw, 10);
}

async function bootstrap(): Promise<number> {
  const [command, ...args] = process.argv.slice(2);
  if (!['app:mail:new-movies', 'app:webhook:purge'].includes(command ?? '')) {
    console.error('Usage: npm run cli -- <app:mail:new-movies|app:webhook:purge> [--days=N]');
    return 2;
  }

  const days = command === 'app:webhook:purge' ? daysOption(args) : 0;
  if (command === 'app:webhook:purge' && (!Number.isInteger(days) || days < 1)) {
    console.error('The --days option must be a positive integer.');
    return 2;
  }

  const app = await NestFactory.createApplicationContext(AppModule);
  try {
    if (command === 'app:mail:new-movies') {
      console.info('Starting process of newly added movies');
      const result = await app.get(NewMoviesService).execute();
      console.info(result.status === 'empty' ? 'No new movie detected' : 'Mail sent');
      return 0;
    }

    const deleted = await app.get(PurgeService).execute(days);
    console.info(`${deleted} webhook(s) older than ${days} day(s) removed.`);
    return 0;
  } finally {
    await app.close();
  }
}

void bootstrap()
  .then((exitCode) => {
    process.exitCode = exitCode;
  })
  .catch((error: unknown) => {
    console.error(error);
    process.exitCode = 1;
  });
