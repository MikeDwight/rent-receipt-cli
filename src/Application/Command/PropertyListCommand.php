<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\Port\PropertyRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PropertyListCommand extends Command
{
    protected static $defaultName = 'property:list';
    protected static $defaultDescription = 'List properties';

    public function __construct(private readonly PropertyRepository $properties)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->properties->listAll();

        if (count($rows) === 0) {
            $output->writeln('<comment>No properties found.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Properties</info>');
        foreach ($rows as $r) {
            $output->writeln(sprintf(
                '  #%d  [%s]  rent=%d  charges=%d',
                (int) $r['id'],
                (string) $r['label'],
                (int) $r['rent_amount'],
                (int) $r['charges_amount']
            ));
        }

        return Command::SUCCESS;
    }
}
