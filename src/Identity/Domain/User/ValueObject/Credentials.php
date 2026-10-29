<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\ValueObject;

use App\Shared\Domain\ValueObject\Email;
use Doctrine\ORM\Mapping as ORM;

/**
 * Credenciais de acesso do usuário.
 *
 * Agrupa o identificador de login e a senha hasheada, isolando o único
 * segredo do agregado. Mapeado como embeddable para manter a tabela `users`
 * como fonte única de verdade nesta fase; quando houver múltiplos provedores
 * de login (redes sociais), este VO passa a ser a credencial local de
 * `LoginIdentity`.
 */
#[ORM\Embeddable]
final readonly class Credentials
{
    private function __construct(
        #[ORM\Column(name: 'email', type: 'email', length: 255, unique: true)]
        private Email $email,

        #[ORM\Column(name: 'password', type: 'hashed_password', length: 255)]
        private HashedPassword $password,
    ) {
    }

    public static function create(Email $email, HashedPassword $password): self
    {
        return new self($email, $password);
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function password(): HashedPassword
    {
        return $this->password;
    }
}
