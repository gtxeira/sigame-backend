<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Console;

use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Rule\LevelCurve;
use App\Shared\Application\User\UserLevelProjectionInterface;
use App\Shared\Application\User\UserLevelReaderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Compara o nível projetado em `users.level` com o saldo de XP.
 *
 * O `--repair` corrige apenas a projeção de Identity. Divergências em
 * `user_progress.level` são encaminhadas para `app:gamification:audit-ledger`.
 */
#[AsCommand(
    name: 'app:gamification:check-level-projection',
    description: 'Compara o nível projetado em users.level com o saldo de XP.',
)]
final class CheckLevelProjectionCommand extends Command
{
    public function __construct(
        private readonly UserProgressRepositoryInterface $progressRepository,
        private readonly UserLevelReaderInterface $levelReader,
        private readonly UserLevelProjectionInterface $projection,
        private readonly LevelCurve $levelCurve,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'repair',
            null,
            InputOption::VALUE_NONE,
            'Corrige users.level a partir do saldo de XP.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repair = (bool) $input->getOption('repair');
        $divergences = [];
        $errors = 0;

        foreach ($this->progressRepository->findAll() as $progress) {
            $userId = $progress->userId()->value();
            $expected = $this->levelCurve->levelFor($progress->xpBalance());
            $projected = $this->levelReader->levelOf($userId);

            if ($projected !== $expected) {
                if ($repair && $this->projection->project($userId, $expected)) {
                    $projected = $expected;
                } else {
                    $divergences[] = sprintf(
                        'user=%s projected=%s expected=%d',
                        $userId,
                        null === $projected ? 'null' : (string) $projected,
                        $expected,
                    );
                    ++$errors;
                }
            }

            if ($progress->level() !== $expected) {
                $divergences[] = sprintf(
                    'user=%s progress_level=%d expected=%d; use app:gamification:audit-ledger --repair',
                    $userId,
                    $progress->level(),
                    $expected,
                );
                ++$errors;
            }
        }

        if ([] === $divergences) {
            $output->writeln('<info>Nenhuma divergência de nível encontrada.</info>');

            return Command::SUCCESS;
        }

        foreach ($divergences as $divergence) {
            $output->writeln('<error>'.$divergence.'</error>');
        }

        $output->writeln(sprintf('<comment>%d divergência(s) encontrada(s).</comment>', count($divergences)));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
