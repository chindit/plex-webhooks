import { Injectable } from '@nestjs/common';
import Handlebars from 'handlebars';
import juice from 'juice';
import type { Movie } from '../plex/movie.js';

export interface DigestMovie {
  movie: Movie;
  thumb: Buffer | null;
}

const TEMPLATE = `
<style>
  .triple-line { display: grid; grid-template-columns: repeat(3, 1fr); }
  .double-line { display: grid; grid-template-columns: 1fr 75%; }
  .is-marged-top { margin-top: 25px; }
  .is-title { font-weight: bold; font-size: x-large; }
  .justify { text-align: justify; }
  .center { margin: 0 auto 10px auto; text-align: center; }
  .align-end { justify-self: center; }
</style>
<body>
  <div class="container">
    <div class="is-title center">Films de la semaine</div>
    <div class="mb-3">Voici les films (et séries) qui ont fait leur arrivée sur le NAS cette semaine:</div>
    {{#each movies}}
      <div class="is-title is-marged-top">{{movie.title}}</div>
      <div class="double-line">
        <div class="align-end"><img src="cid:{{movie.ratingKey}}" class="card-img-top" style="width:150px;" alt="{{movie.title}}"></div>
        <div class="justify">{{movie.summary}}</div>
      </div>
      <div class="triple-line">
        <div>{{movie.year}}</div>
        <div>{{formatDuration movie.duration}}</div>
        <div>{{join movie.genres}}</div>
      </div>
      <div>{{joinFirst movie.actors 5}}</div>
    {{/each}}
  </div>
</body>`;

@Injectable()
export class DigestTemplateService {
  private readonly template: Handlebars.TemplateDelegate;

  constructor() {
    const handlebars = Handlebars.create();
    handlebars.registerHelper('formatDuration', formatDuration);
    handlebars.registerHelper('join', (values: string[]) => values.join(', '));
    handlebars.registerHelper(
      'joinFirst',
      (values: string[], count: number) => values.slice(0, count).join(', '),
    );
    this.template = handlebars.compile(TEMPLATE);
  }

  render(movies: DigestMovie[]): string {
    return juice(this.template({ movies }));
  }
}

export function formatDuration(milliseconds: string | number): string {
  const value = Number(milliseconds);
  const totalSeconds = Number.isFinite(value) ? Math.round(value / 1000) : 0;
  const secondsInDay = ((totalSeconds % 86_400) + 86_400) % 86_400;
  const hours = Math.floor(secondsInDay / 3600);
  const minutes = Math.floor((secondsInDay % 3600) / 60);
  const seconds = secondsInDay % 60;
  return [hours, minutes, seconds].map((part) => String(part).padStart(2, '0')).join(':');
}
