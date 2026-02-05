<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\Port\PropertyRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PropertyShowCommand extends Command
{
    protected static $defaultName = 'property:show';
    protected static $defaultDescription = 'Show a property by id';

    public function __construct(private readonly PropertyRepository $properties)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Property id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $idRaw = (string) $input->getArgument('id');
        if (!ctype_digit($idRaw)) {
            $output->writeln('<error>Invalid id. Expected an integer.</error>');
            return Command::INVALID;
        }

        $id = (int) $idRaw;
        $property = $this->properties->findById($id);

        if ($property === null) {
            $output->writeln("<error>Property not found: #{$id}</error>");
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Property #%d</info>', $property['id']));
        $output->writeln('  Label:    ' . $property['label']);
        $output->writeln('  Owner ID: ' . $property['owner_id']);
        $output->writeln('  Address:  ' . $property['address']);
        $output->writeln('  Rent:     ' . $property['rent_amount']);
        $output->writeln('  Charges:  ' . $property['charges_amount']);
        $output->writeln('  Created:  ' . $property['created_at']);

        return Command::SUCCESS;
    }
}
