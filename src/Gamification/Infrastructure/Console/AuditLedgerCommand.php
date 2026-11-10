<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Console;

use App\Gamification\Application\ReadModel\LedgerAuditReport;
use App\Gamification\Application\ReadModel\LedgerDivergence;
use App\Gamification\Application\ReadModel\LedgerIntegrityIssue;
use App\Gamification\Application\Service\LedgerAuditor;
use App\Gamification\Application\Service\LedgerRepairer;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Shared\Domain\Exception\InvalidUuidException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Audita o ledger de XP contra o progresso materializado e a projeção de nível.
 */
#[AsCommand(
    name: 'app:gamification:audit-ledger',
    description: 'Audita o ledger de XP contra user_progress e users.level.',
)]
final class AuditLedgerCommand extends Command
{
    public function __construct(
        private readonly LedgerAuditor $auditor,
        private readonly LedgerRepairer $repairer,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'Audita apenas o usuário informado.')
            ->addOption('repair', null, InputOption::VALUE_NONE, 'Corrige as divergências encontradas.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = $input->getOption('user-id');
        $playerId = null;

        if (null !== $userId) {
            try {
                $playerId = PlayerId::fromString((string) $userId);
            } catch (InvalidUuidException) {
                $output->writeln('<error>O identificador informado não é um UUID válido.</error>');

                return Command::FAILURE;
            }
        }

        $report = $this->auditor->audit($playerId);

        if ($report->isClean()) {
            $output->writeln(sprintf(
                '<info>Ledger consistente: %d usuário(s) verificado(s).</info>',
                $report->checkedUsers,
            ));

            return Command::SUCCESS;
        }

        $this->printReport($output, $report);

        if (!$input->getOption('repair')) {
            $output->writeln('<comment>Use --repair para corrigir as divergências.</comment>');

            return Command::FAILURE;
        }

        foreach ($report->divergences as $divergence) {
            try {
                $this->repairer->repair($divergence->userId);
                $output->writeln(sprintf('<info>Reparado: %s</info>', $divergence->userId));
            } catch (Throwable $exception) {
                $output->writeln(sprintf(
                    '<error>Falha ao reparar %s: %s</error>',
                    $divergence->userId,
                    $exception->getMessage(),
                ));
            }
        }

        $this->entityManager->clear();
        $after = $this->auditor->audit($playerId);

        if ($after->isClean()) {
            $output->writeln(sprintf(
                '<info>Ledger reparado e consistente: %d usuário(s) verificado(s).</info>',
                $after->checkedUsers,
            ));

            return Command::SUCCESS;
        }

        $this->printReport($output, $after);
        $output->writeln('<error>Algumas divergências permanecem após o reparo.</error>');

        return Command::FAILURE;
    }

    private function printReport(OutputInterface $output, LedgerAuditReport $report): void
    {
        foreach ($report->divergences as $divergence) {
            $output->writeln($this->describeDivergence($divergence));
        }

        foreach ($report->integrityIssues as $issue) {
            $output->writeln($this->describeIntegrityIssue($issue));
        }

        $output->writeln(sprintf(
            '<comment>%d divergência(s), %d problema(s) de integridade em %d usuário(s) verificado(s).</comment>',
            count($report->divergences),
            count($report->integrityIssues),
            $report->checkedUsers,
        ));
    }

    private function describeDivergence(LedgerDivergence $divergence): string
    {
        if ($divergence->isProgressMissing()) {
            return sprintf(
                '<error>user=%s sem user_progress; ledger balance=%d lifetime=%d level=%d projected=%s</error>',
                $divergence->userId,
                $divergence->ledgerBalance,
                $divergence->ledgerLifetime,
                $divergence->ledgerLevel,
                null === $divergence->projectedLevel ? 'null' : (string) $divergence->projectedLevel,
            );
        }

        $parts = [];
        $progressBalance = $divergence->progressBalance ?? 0;
        $progressLifetime = $divergence->progressLifetime ?? 0;
        $progressLevel = $divergence->progressLevel ?? 0;

        if (!$divergence->balanceIsConsistent()) {
            $parts[] = sprintf('balance progress=%d ledger=%d', $progressBalance, $divergence->ledgerBalance);
        }

        if (!$divergence->lifetimeIsConsistent()) {
            $parts[] = sprintf('lifetime progress=%d ledger=%d', $progressLifetime, $divergence->ledgerLifetime);
        }

        if (!$divergence->levelIsConsistent()) {
            $parts[] = sprintf('level progress=%d ledger=%d', $progressLevel, $divergence->ledgerLevel);
        }

        if (!$divergence->projectionIsConsistent()) {
            $parts[] = sprintf(
                'projected=%s expected=%d',
                null === $divergence->projectedLevel ? 'null' : (string) $divergence->projectedLevel,
                $divergence->ledgerLevel,
            );
        }

        return sprintf('<error>user=%s %s</error>', $divergence->userId, implode('; ', $parts));
    }

    private function describeIntegrityIssue(LedgerIntegrityIssue $issue): string
    {
        return sprintf(
            '<error>integridade=%s event=%s user=%s (não reparado automaticamente)</error>',
            $issue->type,
            $issue->eventId,
            $issue->userId,
        );
    }
}
