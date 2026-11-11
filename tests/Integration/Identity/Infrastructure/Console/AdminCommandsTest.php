<?php

declare(strict_types=1);

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\UserRole;
use App\Identity\Infrastructure\Console\GrantAdminCommand;
use App\Identity\Infrastructure\Console\RevokeAdminCommand;
use App\Shared\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function adminCommandTestUser(UserRepositoryInterface $users, string $email): User
{
    $user = User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('$2y$04$hash'),
        name: 'Admin Command Test User',
    );
    $users->save($user);

    return $user;
}

it('promotes a user to admin sigame and is idempotent', function () {
    $users = $this->service(UserRepositoryInterface::class);
    $entityManager = $this->service(EntityManagerInterface::class);
    $user = adminCommandTestUser($users, 'grant-command@example.com');

    $tester = new CommandTester($this->service(GrantAdminCommand::class));

    expect($tester->execute(['email' => 'grant-command@example.com']))->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('agora é administrador SIGAME');

    $entityManager->clear();
    expect($users->findById($user->id())?->role())->toBe(UserRole::ADMIN_SIGAME);

    expect($tester->execute(['email' => 'grant-command@example.com']))->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('já é administrador SIGAME');
});

it('rejects an invalid email when granting the admin role', function () {
    $tester = new CommandTester($this->service(GrantAdminCommand::class));

    expect($tester->execute(['email' => 'not-an-email']))->toBe(Command::INVALID)
        ->and($tester->getDisplay())->toContain('E-mail inválido.');
});

it('fails to grant the admin role to an unknown user', function () {
    $tester = new CommandTester($this->service(GrantAdminCommand::class));

    expect($tester->execute(['email' => 'ghost@example.com']))->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('Usuário não encontrado.');
});

it('demotes an admin sigame while preserving another active admin', function () {
    $users = $this->service(UserRepositoryInterface::class);
    $entityManager = $this->service(EntityManagerInterface::class);
    $firstAdmin = adminCommandTestUser($users, 'revoke-first@example.com');
    $firstAdmin->grantAdminRole();
    $users->save($firstAdmin);

    $secondAdmin = adminCommandTestUser($users, 'revoke-second@example.com');
    $secondAdmin->grantAdminRole();
    $users->save($secondAdmin);

    $tester = new CommandTester($this->service(RevokeAdminCommand::class));

    expect($tester->execute(['email' => 'revoke-first@example.com']))->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('agora é usuário comum')
        ->and($users->countActiveAdmins())->toBe(1);

    $entityManager->clear();
    expect($users->findById($firstAdmin->id())?->role())->toBe(UserRole::USER);
});

it('refuses to demote the last active admin sigame', function () {
    $users = $this->service(UserRepositoryInterface::class);
    $admin = adminCommandTestUser($users, 'last-admin@example.com');
    $admin->grantAdminRole();
    $users->save($admin);

    $tester = new CommandTester($this->service(RevokeAdminCommand::class));

    expect($tester->execute(['email' => 'last-admin@example.com']))->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('último administrador ativo')
        ->and($admin->role())->toBe(UserRole::ADMIN_SIGAME);
});

it('does nothing when revoking the role from a regular user', function () {
    adminCommandTestUser($this->service(UserRepositoryInterface::class), 'regular-revoke@example.com');

    $tester = new CommandTester($this->service(RevokeAdminCommand::class));

    expect($tester->execute(['email' => 'regular-revoke@example.com']))->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('não é administrador SIGAME');
});

it('rejects an invalid email when revoking the admin role', function () {
    $tester = new CommandTester($this->service(RevokeAdminCommand::class));

    expect($tester->execute(['email' => 'not-an-email']))->toBe(Command::INVALID)
        ->and($tester->getDisplay())->toContain('E-mail inválido.');
});

it('fails to revoke the admin role from an unknown user', function () {
    $tester = new CommandTester($this->service(RevokeAdminCommand::class));

    expect($tester->execute(['email' => 'ghost@example.com']))->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('Usuário não encontrado.');
});
