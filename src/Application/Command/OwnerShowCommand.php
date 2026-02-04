<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\Port\OwnerRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class OwnerShowCommand extends Command
{
    protected static $defaultName = 'owner:show';
    protected static $defaultDescription = 'Show a single owner by id';

    public function __construct(private readonly OwnerRepository $owners)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Owner id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $idRaw = (string) $input->getArgument('id');
        if (!ctype_digit($idRaw)) {
            $output->writeln('<error>Invalid id. Expected an integer.</error>');
            return Command::INVALID;
        }

        $id = (int) $idRaw;
        $owner = $this->owners->findById($id);

        if ($owner === null) {
            $output->writeln("<error>Owner not found: #{$id}</error>");
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Owner #%d</info>', $owner['id']));
        $output->writeln('  Name:    ' . $owner['full_name']);
        $output->writeln('  Email:   ' . $owner['email']);
        $output->writeln('  Address: ' . $owner['address']);
        $output->writeln('  Created: ' . $owner['created_at']);

        return Command::SUCCESS;
    }
}
