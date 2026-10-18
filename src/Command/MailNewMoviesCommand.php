<?php

namespace App\Command;

use App\Repository\PlexWebhookRepository;
use Chindit\PlexApi\Model\Movie;
use Chindit\PlexApi\PlexServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Twig\Environment;

#[AsCommand(
    name: 'app:mail:new-movies',
    description: 'Sends a digest email of the movies added to Plex during the last week',
)]
class MailNewMoviesCommand extends Command
{
    public function __construct(
        private readonly PlexWebhookRepository $webhookRepository,
        private readonly Environment $templateEngine,
        private readonly MailerInterface $mailer,
        private readonly array $destination,
        private readonly string $sender,
        private readonly PlexServer $plexServer
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info('Starting process of newly added movies');

        $canConnectToPlex = $this->plexServer->checkConnection();
        $newMovies = [];
        $seenEpisodes = [];

        foreach ($this->webhookRepository->findNewMoviesFromLastWeek() as $webhook) {
            $movie = null;
            $guid = $webhook->getGuid();
            if ($guid === null) {
                continue;
            }

            $metadata = $webhook->getMetadata();
            $isEpisode = ($metadata['type'] ?? null) === 'episode';
            $itemKey = 'movie:' . ($metadata['ratingKey'] ?? $guid);
            $episodeKey = (string) ($metadata['ratingKey'] ?? $guid);

            if ($isEpisode) {
                // The grandparent is the show; the parent only identifies the season.
                $seriesKey = $metadata['grandparentRatingKey'] ?? $metadata['grandparentGuid'] ?? null;
                if ($seriesKey === null && isset($metadata['grandparentTitle'])) {
                    $seriesKey = json_encode([
                        $metadata['librarySectionID'] ?? null,
                        $metadata['grandparentTitle'],
                    ]);
                }
                $itemKey = 'show:' . ($seriesKey ?? $episodeKey);

                if (isset($seenEpisodes[$itemKey][$episodeKey])) {
                    continue;
                }

                if (isset($newMovies[$itemKey])) {
                    ++$newMovies[$itemKey]['episodeCount'];
                    $newMovies[$itemKey]['thumb'] ??= $webhook->getThumb();
                    $seenEpisodes[$itemKey][$episodeKey] = true;
                    continue;
                }
            }

            if (str_starts_with($guid, 'local')) {
                if ($canConnectToPlex) {
                    $movie = $this->plexServer->getFromKey($metadata['ratingKey']);
                }
            } else {
                $movie = $webhook->asMovie();
            }

            if (!$movie) {
                continue;
            }

            $newMovies[$itemKey] = [
                'movie' => $movie,
                'thumb' => $webhook->getThumb(),
                'title' => $isEpisode ? ($metadata['grandparentTitle'] ?? $movie->getTitle()) : $movie->getTitle(),
                'episodeCount' => $isEpisode ? 1 : 0,
            ];
            if ($isEpisode) {
                $seenEpisodes[$itemKey][$episodeKey] = true;
            }
        }

        if ($newMovies === []) {
            $io->success('No new movie detected');

            return Command::SUCCESS;
        }

        $email = (new Email())
            ->from(new Address($this->sender, 'David'))
            ->to(...$this->destination)
            ->subject('Les films de la semaine sur DadaNAS');

        foreach ($newMovies as $item) {
            /** @var Movie $movie */
            $movie = $item['movie'];
            if ($item['thumb']) {
                $email->addPart((new DataPart($item['thumb'], $movie->getRatingKey(), 'image/jpeg'))->asInline());
            }
        }

        $this->mailer->send(
            $email
                ->html($this->templateEngine->render('base.html.twig', ['movies' => $newMovies]))
        );

        $io->success('Mail sent');

        return Command::SUCCESS;
    }
}
