<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\Port\PropertyRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class PropertyDeleteCommand extends Command
{
    protected static $defaultName = 'property:delete';
    protected static $defaultDescription = 'Delete a property (with safeguards)';

    public function __construct(private readonly PropertyRepository $properties)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'Property id')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Do not ask for confirmation');
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

        $payments = $this->properties->countPaymentsForProperty($id);
        if ($payments > 0) {
            $output->writeln("<error>Cannot delete property #{$id}.</error>");
            $output->writeln("Reason: property is linked to <comment>{$payments}</comment> payment(s).");
            $output->writeln("<comment>Hint:</comment> delete payments first, then retry.");
            return Command::FAILURE;
        }

        if (!$input->getOption('force')) {
            $helper = $this->getHelper('question');
            $q = new ConfirmationQuestion(
                sprintf('Delete property #%d (%s)? [y/N] ', $id, (string) $property['label']),
                false
            );

            if (!$helper->ask($input, $output, $q)) {
                $output->writeln('<comment>Aborted.</comment>');
                return Command::SUCCESS;
            }
        }

        $this->properties->delete($id);
        $output->writeln("<info>Deleted property #{$id}</info>");

        return Command::SUCCESS;
    }
}
