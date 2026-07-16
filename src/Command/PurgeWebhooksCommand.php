<?php

namespace App\Command;

use App\Repository\PlexWebhookRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:webhook:purge',
    description: 'Removes stored webhooks (and their thumbnails) older than the given number of days',
)]
class PurgeWebhooksCommand extends Command
{
    private const DEFAULT_RETENTION_DAYS = 30;

    public function __construct(
        private readonly PlexWebhookRepository $webhookRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            'd',
            InputOption::VALUE_REQUIRED,
            'Delete webhooks older than this many days',
            self::DEFAULT_RETENTION_DAYS,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) $input->getOption('days');
        if ($days < 1) {
            $io->error('The --days option must be a positive integer.');

            return Command::INVALID;
        }

        $threshold = (new \DateTimeImmutable())->sub(new \DateInterval(sprintf('P%dD', $days)));
        $deleted = $this->webhookRepository->deleteOlderThan($threshold);

        $io->success(sprintf('%d webhook(s) older than %d day(s) removed.', $deleted, $days));

        return Command::SUCCESS;
    }
}
