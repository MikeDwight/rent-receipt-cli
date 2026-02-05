<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\Port\OwnerRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class OwnerDeleteCommand extends Command
{
    protected static $defaultName = 'owner:delete';
    protected static $defaultDescription = 'Delete an owner (with safeguards)';

    public function __construct(private readonly OwnerRepository $owners)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'Owner id')
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
        $owner = $this->owners->findById($id);

        if ($owner === null) {
            $output->writeln("<error>Owner not found: #{$id}</error>");
            return Command::FAILURE;
        }

        $propsCount = $this->owners->countPropertiesForOwner($id);
        if ($propsCount > 0) {
            $output->writeln("<error>Cannot delete owner #{$id}.</error>");
            $output->writeln("Reason: owner is linked to <comment>{$propsCount}</comment> propert(ies).");
            $output->writeln("<comment>Hint:</comment> reassign properties to another owner, then retry.");
            return Command::FAILURE;
        }

        $force = (bool) $input->getOption('force');
        if (!$force) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                sprintf('Delete owner #%d (%s)? [y/N] ', $id, (string) $owner['full_name']),
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Aborted.</comment>');
                return Command::SUCCESS;
            }
        }

        $this->owners->delete($id);
        $output->writeln("<info>Deleted owner #{$id}</info>");

        return Command::SUCCESS;
    }
}
