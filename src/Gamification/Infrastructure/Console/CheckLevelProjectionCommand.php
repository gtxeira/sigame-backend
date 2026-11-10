<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Console;

use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Rule\LevelCurve;
use App\Shared\Application\User\UserLevelReaderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Compara o nível projetado em `users.level` com o saldo de XP.
 *
 * Não corrige divergências: apenas reporta para que a causa seja investigada.
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
        private readonly LevelCurve $levelCurve,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $divergences = [];

        foreach ($this->progressRepository->findAll() as $progress) {
            $userId = $progress->userId()->value();
            $projected = $this->levelReader->levelOf($userId);
            $expected = $this->levelCurve->levelFor($progress->xpBalance());

            if (null === $projected) {
                $divergences[] = sprintf('user=%s sem projeção em users.level', $userId);

                continue;
            }

            if ($projected !== $expected || $progress->level() !== $expected) {
                $divergences[] = sprintf(
                    'user=%s balance=%d progress_level=%d projected_level=%d expected_level=%d',
                    $userId,
                    $progress->xpBalance(),
                    $progress->level(),
                    $projected,
                    $expected,
                );
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

        return Command::FAILURE;
    }
}
