<?php

namespace App\Command;

use App\Entity\PlexWebhook;
use App\Repository\PlexWebhookRepository;
use Chindit\Collection\Collection;
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
    description: 'Add a short description for your command',
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

        /** @var Collection<PlexWebhook> $newMovies */
        $newMovies = new Collection($this->webhookRepository->findNewMoviesFromLastWeek());

		$canConnectToPlex = $this->plexServer->checkConnection();
		$newMovies = $newMovies->map(function(PlexWebhook $webhook) use ($canConnectToPlex) {
            $movie = null;
			if (str_starts_with($webhook->getContent()['Metadata']['guid'], 'local')) {
				if ($canConnectToPlex) {
				    $movie = $this->plexServer->getFromKey($webhook->getContent()['Metadata']['ratingKey']);
				}
			} else {
                $movie = $webhook->asMovie();
            }

            if (!$movie) {
                return null;
            }

			return [
                'movie' => $movie,
                'thumb' => $webhook->getThumb(),
            ];
		})
			->filter()
			->keyBy(fn(array $item) => $item['movie']->getRatingKey());

	    if ($newMovies->isEmpty()) {
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
