<?php

namespace App\Command;

use App\Entity\PlexWebhook;
use App\Repository\PlexWebhookRepository;
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
        private readonly array $destination
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info('Starting process of newly added movied');

        /** @var PlexWebhook[] $newMovies */
        $newMovies = $this->webhookRepository->findNewMoviesFromLastWeek();

        $email = (new Email())
            ->from(new Address('d.lumaye@mailtunnel.eu', 'David'))
            ->to(...$this->destination)
            ->subject('Les films de la semaine sur DadaNAS');

        foreach ($newMovies as $movie) {
            $email->addPart((new DataPart($movie->getThumb(), $movie->getContent()['Metadata']['ratingKey'], 'image/jpeg'))->asInline());
        }

        $this->mailer->send(
            $email
                ->html($this->templateEngine->render('base.html.twig', ['movies' => $newMovies]))
        );

        $io->success('Mail sent');

        return Command::SUCCESS;
    }
}
