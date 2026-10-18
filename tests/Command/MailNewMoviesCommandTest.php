<?php

namespace App\Tests\Command;

use App\Command\MailNewMoviesCommand;
use App\Entity\PlexWebhook;
use App\Repository\PlexWebhookRepository;
use Chindit\PlexApi\PlexServer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Extra\CssInliner\CssInlinerExtension;
use Twig\Loader\FilesystemLoader;

class MailNewMoviesCommandTest extends TestCase
{
    public function testCombinesShowSeasonsAndEpisodesAndPreservesShowDetailsInAnyOrder(): void
    {
        $show = (new PlexWebhook())->setContent(['Metadata' => [
            'type' => 'show',
            'ratingKey' => '100',
            'guid' => 'plex://show/100',
            'title' => 'Example Series',
            'summary' => 'The show summary',
            'year' => 2020,
            'Genre' => [['tag' => 'Drama']],
            'Role' => [['tag' => 'An Actor']],
        ]])->setThumb('show thumbnail');
        $season = (new PlexWebhook())->setContent(['Metadata' => [
            'type' => 'season',
            'ratingKey' => '20',
            'guid' => 'plex://season/20',
            'title' => 'Season 1',
            'summary' => 'The season summary',
            'parentRatingKey' => '100',
            'parentGuid' => 'plex://show/100',
            'parentTitle' => 'Example Series',
        ]])->setThumb('season thumbnail');
        $webhooks = [$show, $season, $this->episode(), $this->episode([
            'ratingKey' => '11',
            'guid' => 'plex://episode/11',
            'parentRatingKey' => '21',
        ]), $show, $season, $this->episode()];

        foreach ([$webhooks, array_reverse($webhooks)] as $events) {
            $email = $this->sendDigest($events);
            $html = $email->getHtmlBody();

            self::assertSame(1, substr_count($html, '>Example Series</div>'));
            self::assertStringContainsString('2 épisodes ajoutés', $html);
            self::assertStringContainsString('The show summary', $html);
            self::assertStringContainsString('2020', $html);
            self::assertStringContainsString('Drama', $html);
            self::assertStringContainsString('An Actor', $html);
            self::assertStringNotContainsString('The season summary', $html);
            self::assertStringNotContainsString('Episode summary', $html);
            self::assertCount(1, $email->getAttachments());
            self::assertSame('show thumbnail', $email->getAttachments()[0]->getBody());
            self::assertStringContainsString('cid:100', $html);
        }
    }

    public function testCombinesEventsWhenSomeOnlyHaveTheShowGuidOrTitle(): void
    {
        $show = (new PlexWebhook())->setContent(['Metadata' => [
            'type' => 'show',
            'ratingKey' => '100',
            'guid' => 'plex://show/100',
            'title' => 'Example Series',
            'summary' => 'The show summary',
        ]]);
        $email = $this->sendDigest([
            $this->episode(['grandparentRatingKey' => null, 'grandparentGuid' => 'plex://show/100']),
            $show,
            $this->episode(['ratingKey' => '11', 'guid' => 'plex://episode/11', 'grandparentRatingKey' => null]),
        ]);

        self::assertSame(1, substr_count($email->getHtmlBody(), '>Example Series</div>'));
        self::assertStringContainsString('2 épisodes ajoutés', $email->getHtmlBody());
        self::assertStringContainsString('The show summary', $email->getHtmlBody());
    }

    public function testShowWithoutGuidStillSuppliesDetailsAndNormalizesLibraryIds(): void
    {
        $show = (new PlexWebhook())->setContent(['Metadata' => [
            'type' => 'show',
            'ratingKey' => '100',
            'title' => 'Example Series',
            'summary' => 'The show summary',
            'librarySectionID' => 1,
        ]]);
        $email = $this->sendDigest([
            $show,
            $this->episode(['librarySectionID' => '1']),
            $this->episode([
                'ratingKey' => '11',
                'guid' => 'plex://episode/11',
                'grandparentRatingKey' => null,
                'grandparentGuid' => 'plex://show/100',
                'librarySectionID' => '1',
            ]),
        ]);

        self::assertSame(1, substr_count($email->getHtmlBody(), '>Example Series</div>'));
        self::assertStringContainsString('2 épisodes ajoutés', $email->getHtmlBody());
        self::assertStringContainsString('The show summary', $email->getHtmlBody());
    }

    public function testKeepsCompleteShowCardsWithTheSameTitleSeparate(): void
    {
        $webhooks = [];
        foreach (['100', '200'] as $id) {
            $webhooks[] = (new PlexWebhook())->setContent(['Metadata' => [
                'type' => 'show',
                'ratingKey' => $id,
                'guid' => 'plex://show/' . $id,
                'title' => 'Example Series',
                'summary' => 'Show summary ' . $id,
            ]]);
            $webhooks[] = $this->episode([
                'ratingKey' => $id . '1',
                'guid' => 'plex://episode/' . $id . '1',
                'grandparentRatingKey' => $id,
            ]);
        }
        $email = $this->sendDigest($webhooks);

        self::assertSame(2, substr_count($email->getHtmlBody(), '>Example Series</div>'));
        self::assertSame(2, substr_count($email->getHtmlBody(), '1 épisode ajouté'));
        self::assertStringContainsString('Show summary 100', $email->getHtmlBody());
        self::assertStringContainsString('Show summary 200', $email->getHtmlBody());
    }

    public function testGroupsFortyEpisodesAcrossSeasonsAndIgnoresDuplicateWebhooks(): void
    {
        $webhooks = [];
        for ($i = 1; $i <= 40; ++$i) {
            $webhooks[] = $this->episode([
                'ratingKey' => (string) (1000 + $i),
                'guid' => 'plex://episode/' . $i,
                'parentRatingKey' => (string) (200 + intdiv($i - 1, 10)),
                'parentIndex' => 1 + intdiv($i - 1, 10),
                'index' => 1 + ($i - 1) % 10,
            ]);
        }
        $webhooks[1]->setThumb('thumbnail');
        $webhooks[] = $webhooks[0];

        $email = $this->sendDigest($webhooks);
        $html = $email->getHtmlBody();

        self::assertSame(1, substr_count($html, '>Example Series</div>'));
        self::assertStringContainsString('40 épisodes ajoutés', $html);
        self::assertStringNotContainsString('Episode title', $html);
        self::assertStringNotContainsString('Episode summary', $html);
        self::assertCount(1, $email->getAttachments());
        self::assertStringContainsString('cid:1001', $html);
    }

    public function testKeepsDifferentSeriesWithTheSameTitleAndMoviesSeparate(): void
    {
        $movie = (new PlexWebhook())->setContent(['Metadata' => [
            'type' => 'movie',
            'ratingKey' => '100',
            'guid' => 'plex://movie/100',
            'title' => 'A Movie',
            'summary' => 'Movie summary',
        ]]);
        $email = $this->sendDigest([
            $this->episode(),
            $this->episode(['ratingKey' => '11', 'guid' => 'plex://episode/11']),
            $this->episode(['ratingKey' => '12', 'guid' => 'plex://episode/12', 'grandparentRatingKey' => '200']),
            $movie,
            $movie,
        ]);
        $html = $email->getHtmlBody();

        self::assertSame(2, substr_count($html, '>Example Series</div>'));
        self::assertStringContainsString('2 épisodes ajoutés', $html);
        self::assertStringContainsString('1 épisode ajouté', $html);
        self::assertSame(1, substr_count($html, '>A Movie</div>'));
        self::assertStringContainsString('Movie summary', $html);
        self::assertStringNotContainsString('cid:', $html);
    }

    public function testFallsBackToSeriesTitleWithinTheSameLibrary(): void
    {
        $email = $this->sendDigest([
            $this->episode(['grandparentRatingKey' => null, 'librarySectionID' => 1]),
            $this->episode([
                'ratingKey' => '11',
                'guid' => 'plex://episode/11',
                'grandparentRatingKey' => null,
                'parentRatingKey' => '21',
                'librarySectionID' => 1,
            ]),
            $this->episode([
                'ratingKey' => '12',
                'guid' => 'plex://episode/12',
                'grandparentRatingKey' => null,
                'librarySectionID' => 2,
            ]),
        ]);
        $html = $email->getHtmlBody();

        self::assertSame(2, substr_count($html, '>Example Series</div>'));
        self::assertStringContainsString('2 épisodes ajoutés', $html);
        self::assertStringContainsString('1 épisode ajouté', $html);
    }

    public function testLocalEpisodesAreGroupedBeforeFetchingPlexMetadata(): void
    {
        $first = $this->episode(['guid' => 'local://episode/10']);
        $plexServer = $this->createMock(PlexServer::class);
        $plexServer->method('checkConnection')->willReturn(true);
        $plexServer->expects(self::once())->method('getFromKey')->with('10')->willReturn($first->asMovie());

        $email = $this->sendDigest([
            $first,
            $this->episode(['ratingKey' => '11', 'guid' => 'local://episode/11']),
            $first,
        ], $plexServer);

        self::assertStringContainsString('2 épisodes ajoutés', $email->getHtmlBody());
    }

    public function testDoesNotSendAnEmailWhenThereAreNoNewItems(): void
    {
        $repository = $this->createStub(PlexWebhookRepository::class);
        $repository->method('findNewMoviesFromLastWeek')->willReturn([]);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        $command = new MailNewMoviesCommand(
            $repository,
            $this->createStub(Environment::class),
            $mailer,
            ['recipient@example.com'],
            'sender@example.com',
            $this->createStub(PlexServer::class),
        );

        self::assertSame(Command::SUCCESS, (new CommandTester($command))->execute([]));
    }

    private function episode(array $metadata = []): PlexWebhook
    {
        return (new PlexWebhook())->setContent(['Metadata' => array_replace([
            'type' => 'episode',
            'ratingKey' => '10',
            'guid' => 'plex://episode/10',
            'title' => 'Episode title',
            'summary' => 'Episode summary',
            'grandparentRatingKey' => '100',
            'grandparentTitle' => 'Example Series',
            'parentRatingKey' => '20',
        ], $metadata)]);
    }

    /** @param PlexWebhook[] $webhooks */
    private function sendDigest(array $webhooks, ?PlexServer $plexServer = null): Email
    {
        $repository = $this->createStub(PlexWebhookRepository::class);
        $repository->method('findNewMoviesFromLastWeek')->willReturn($webhooks);
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'), ['strict_variables' => true]);
        $twig->addExtension(new CssInlinerExtension());
        if ($plexServer === null) {
            $plexServer = $this->createMock(PlexServer::class);
            $plexServer->method('checkConnection')->willReturn(false);
            $plexServer->expects(self::never())->method('getFromKey');
        }
        $email = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(
            static function (Email $message) use (&$email): void {
                $email = $message;
            }
        );
        $command = new MailNewMoviesCommand(
            $repository,
            $twig,
            $mailer,
            ['recipient@example.com'],
            'sender@example.com',
            $plexServer,
        );

        self::assertSame(Command::SUCCESS, (new CommandTester($command))->execute([]));
        self::assertInstanceOf(Email::class, $email);

        return $email;
    }
}
