<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\Port\TenantRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class TenantDeleteCommand extends Command
{
    protected static $defaultName = 'tenant:delete';
    protected static $defaultDescription = 'Delete a tenant (with safeguards)';

    public function __construct(private readonly TenantRepository $tenants)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'Tenant id')
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
        $tenant = $this->tenants->findById($id);

        if ($tenant === null) {
            $output->writeln("<error>Tenant not found: #{$id}</error>");
            return Command::FAILURE;
        }

        $paymentsCount = $this->tenants->countPaymentsForTenant($id);
        if ($paymentsCount > 0) {
            $output->writeln("<error>Cannot delete tenant #{$id}.</error>");
            $output->writeln("Reason: tenant is linked to <comment>{$paymentsCount}</comment> payment(s).");
            $output->writeln("<comment>Hint:</comment> delete payments first, then retry.");
            return Command::FAILURE;
        }

        $force = (bool) $input->getOption('force');
        if (!$force) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                sprintf('Delete tenant #%d (%s)? [y/N] ', $id, (string) $tenant['full_name']),
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Aborted.</comment>');
                return Command::SUCCESS;
            }
        }

        $this->tenants->delete($id);
        $output->writeln("<info>Deleted tenant #{$id}</info>");

        return Command::SUCCESS;
    }
}
