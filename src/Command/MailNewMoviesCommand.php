<?php

namespace App\Command;

use App\Entity\PlexWebhook;
use App\Plex\PlexClient;
use App\Repository\PlexWebhookRepository;
use Chindit\PlexApi\Model\Movie;
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
        private readonly PlexClient $plexClient
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info('Starting process of newly added movies');

        $newMovies = [];

        $groups = $this->groupWebhooks($this->webhookRepository->findNewMoviesFromLastWeek());
        foreach ($groups as $group) {
            // Prefer the show's own description and poster, regardless of event order.
            usort($group['webhooks'], fn (PlexWebhook $a, PlexWebhook $b) =>
                ($this->seriesIdentity($b)['priority'] ?? 0) <=> ($this->seriesIdentity($a)['priority'] ?? 0)
            );
            $movie = null;
            foreach ($group['webhooks'] as $webhook) {
                $guid = $webhook->getGuid();
                if ($guid === null && !$group['isSeries']) {
                    continue;
                }
                if ($guid !== null && str_starts_with($guid, 'local')) {
                    $movie = $this->plexClient->getFromKey($webhook->getMetadata()['ratingKey']);
                } else {
                    $movie = $webhook->asMovie();
                }
                if ($movie !== null) {
                    break;
                }
            }

            if (!$movie) {
                continue;
            }

            $thumb = $webhook->getThumb();
            foreach ($group['webhooks'] as $candidate) {
                $thumb ??= $candidate->getThumb();
            }
            $newMovies[] = [
                'movie' => $movie,
                'thumb' => $thumb,
                'title' => $this->seriesIdentity($webhook)['title'] ?? $movie->getTitle(),
                'episodeCount' => count($group['episodes']),
                'hasDetails' => !$group['isSeries'] || $webhook->getMetadata()['type'] === 'show',
            ];
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

    /**
     * @param PlexWebhook[] $webhooks
     * @return array<string, array{webhooks: PlexWebhook[], episodes: array<string, true>, isSeries: bool}>
     */
    private function groupWebhooks(array $webhooks): array
    {
        $entries = [];
        $aliases = [];
        foreach ($webhooks as $webhook) {
            $metadata = $webhook->getMetadata();
            $identity = $this->seriesIdentity($webhook);
            $key = 'movie:' . ($metadata['ratingKey'] ?? $webhook->getGuid());
            if ($identity !== null) {
                $field = $identity['ratingKey'] !== null ? 'ratingKey' : ($identity['guid'] !== null ? 'guid' : 'title');
                $key = json_encode([$identity['scope'], $field, $identity[$field] ?? $metadata['ratingKey'] ?? $webhook->getGuid()]);
                $rank = match ($field) { 'ratingKey' => 2, 'guid' => 1, default => 0 };
                foreach (['guid', 'title'] as $alias) {
                    if ($identity[$alias] !== null) {
                        $aliases[$identity['scope']][$alias][$identity[$alias]][$key] = $rank;
                    }
                }
            }
            $entries[] = ['webhook' => $webhook, 'identity' => $identity, 'key' => $key];
        }

        $groups = [];
        foreach ($entries as $entry) {
            ['webhook' => $webhook, 'identity' => $identity, 'key' => $key] = $entry;
            if ($identity !== null && $identity['ratingKey'] === null) {
                // Resolve incomplete events against all known shows, without merging namesakes.
                foreach (['guid', 'title'] as $alias) {
                    $candidates = $aliases[$identity['scope']][$alias][$identity[$alias] ?? ''] ?? [];
                    if ($candidates === []) {
                        continue;
                    }
                    $bestRank = max($candidates);
                    $candidates = array_filter($candidates, static fn (int $rank) => $rank === $bestRank);
                    if (count($candidates) === 1) {
                        $resolvedKey = array_key_first($candidates);
                        if ($resolvedKey !== $entry['key'] || $alias === 'title') {
                            $key = $resolvedKey;
                            break;
                        }
                    }
                }
            }
            $groups[$key] ??= ['webhooks' => [], 'episodes' => [], 'isSeries' => $identity !== null];
            $groups[$key]['webhooks'][] = $webhook;
            $metadata = $webhook->getMetadata();
            if (($metadata['type'] ?? null) === 'episode') {
                $episodeKey = $metadata['ratingKey'] ?? $webhook->getGuid();
                if ($episodeKey !== null) {
                    $groups[$key]['episodes'][$episodeKey] = true;
                }
            }
        }

        return $groups;
    }

    /** @return array{ratingKey: ?string, guid: ?string, title: ?string, scope: string, priority: int}|null */
    private function seriesIdentity(PlexWebhook $webhook): ?array
    {
        $metadata = $webhook->getMetadata();
        // Show events identify themselves; seasons refer to their parent, episodes to their grandparent.
        $prefix = match ($metadata['type'] ?? null) {
            'show' => '',
            'season' => 'parent',
            'episode' => 'grandparent',
            default => null,
        };
        if ($prefix === null) {
            return null;
        }
        $identity = [];
        foreach (['ratingKey', 'guid', 'title'] as $field) {
            $value = $metadata[$prefix === '' ? $field : $prefix . ucfirst($field)] ?? null;
            $identity[$field] = $value !== null && $value !== '' ? (string) $value : null;
        }
        $identity['scope'] = json_encode([
            $webhook->getContent()['Server']['uuid'] ?? null,
            isset($metadata['librarySectionID']) ? (string) $metadata['librarySectionID'] : null,
        ]);
        $identity['priority'] = match ($prefix) { '' => 2, 'parent' => 1, default => 0 };

        return $identity;
    }
}
