<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\Port\OwnerRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class OwnerListCommand extends Command
{
    protected static $defaultName = 'owner:list';
    protected static $defaultDescription = 'List owners';

    public function __construct(private readonly OwnerRepository $owners)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->owners->listAll();

        if (count($rows) === 0) {
            $output->writeln('<comment>No owners found.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Owners</info>');
            foreach ($rows as $r) {
            $email = trim((string) $r['email']);

            $line = sprintf(
                '  #%d  %s',
                (int) $r['id'],
                (string) $r['full_name']
            );

            if ($email !== '') {
                $line .= sprintf('  <%s>', $email);
            }

            $output->writeln($line);
        }


        return Command::SUCCESS;
    }
}
