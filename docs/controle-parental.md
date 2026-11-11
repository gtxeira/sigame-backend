# SIGAME — Controle parental e uso por menores

> **Status:** arquivo de planejamento não aplicado
>
> **Última atualização:** 2026-11-11
>
> **Finalidade:** registrar o que foi planejado para menores de idade e **não** entrou na V1, por que ficou de fora e
> como retomar cada item. A regra atual está em [requisitos/levantamento-de-requisitos.md](requisitos/levantamento-de-requisitos.md)
> (`RF01`, `RF03`) e em [decisoes.md](decisoes.md) (`ADR-044`).

## 1. Regra vigente da V1

- A plataforma é **restrita a maiores de 18 anos**.
- A data de nascimento é **write-once**, definida por `PATCH /api/v1/users/me/birth-date`, e é pré-requisito do
  onboarding.
- Conta ainda incompleta (sem data de nascimento e sem onboarding) que informe idade inferior a 18 é **removida**.
- **Não existe** modo menor, consentimento de responsável, dependente, acompanhante ou cadastro de menores.
- Menores não são usuários da plataforma.

## 2. Princípios que continuam válidos se o escopo mudar

Estes pontos não foram descartados: são pré-requisitos de qualquer versão futura que volte a aceitar menores.

- **E-mail não prova tutela.** Um aceite enviado por e-mail comprova apenas o controle da caixa postal informada. O
  sistema não deve registrar nem exibir "tutela verificada".
- **Nenhuma capacidade é liberada sem registro formal.** Sem aceite vigente e versionado, o menor não compete, não
  aparece em ranking e não resgata prêmio.
- **Comprovação de tutela é do prêmio, não da conta.** Quando a lei ou o regulamento da premiação exigirem, a prova é
  feita no resgate, em processo à parte.
- **Menor nunca tem projeção pública completa.** Nome, foto, peso, altura e cidade exata ficam fora de qualquer
  listagem pública.
- **Se ninguém age sobre o dado, não colete.** Dado de menor só entra se mudar capacidade, segurança ou obrigação
  legal.

## 3. Planejamento não aplicado

### 3.1 Faixas de idade com capacidades parciais

**O que era:** `AgeBand` (`<13`, `13–15`, `16–17`, `18+`) com capacidades e exposição diferentes por faixa, em vez de
bloqueio binário.

**Por que ficou fora:** exigiria consentimento de responsável, projeção pública reduzida e fluxo de premiação; a V1
opta por restringir o uso a maiores de 18 e eliminar o problema na origem.

**Como retomar:**

1. trocar `AgePolicy::MINIMUM_AGE` de 18 para 13 e introduzir `AgeBand` em `Shared/Domain/ValueObject`;
2. derivar a faixa de `BirthDate::ageAt()` (já implementado) e não persistir a faixa;
3. ajustar `AssignBirthDateHandler`: deixar de remover a conta em `<18` e passar a marcar a conta como pendente de
   consentimento;
4. atualizar `RF01`/`RF03` e os testes de faixa.

### 3.2 Consentimento do responsável por e-mail

**O que era:** agregado `GuardianConsent` com escopo (`COMPETITION`, `PRIZE`), contato do responsável (somente
e-mail), token de uso único (hash persistido, expiração de 7 dias), versão do termo, evidência de origem (IP, user
agent), reenvio limitado e revogação.

**Por que ficou fora:** sem menores como usuários, não há consentimento a coletar. Coletar e-mail de terceiro sem
finalidade seria passivo de LGPD.

**Como retomar:**

1. criar `Identity/Domain/GuardianConsent` com `GuardianConsentId`, `ConsentScope`, `GuardianConsentStatus`,
   `TermVersion` e `GuardianEmail`;
2. criar a tabela `guardian_consents` com índice parcial único de um `PENDING` por `(user_id, scope)`;
3. adicionar `dispatchGuardianConsent()` em `IdentityMailDispatcherInterface`, mensagem assíncrona e template;
4. expor `POST /api/v1/auth/guardian-consent/{token}` (público) e `POST /api/v1/auth/guardian-consent/resend`
   (`ROLE_USER`), com rate limiter próprio;
5. registrar no aceite: data, versão do termo, escopo e origem; nunca um campo de tutela verificada.

### 3.3 Onboarding aguardando consentimento

**O que era:** `OnboardingStatus::AWAITING_GUARDIAN_CONSENT`. O menor concluía o onboarding, mas só recebia nível 2 e o
evento `OnboardingCompleted` (que concede XP na Gamification) após o aceite.

**Por que ficou fora:** não existe onboarding de menor.

**Como retomar:** ampliar o enum, fazer `User::completeOnboarding()` decidir entre `COMPLETED` e `AWAITING`, e só
disparar `OnboardingCompleted` no aceite — assim a Gamification continua sem conhecer idade.

### 3.4 Exposição mínima de menores

**O que era:** `MinorAudiencePolicy` decidindo que menor aparece apenas por apelido, sem foto, sem biometria e sem
cidade exata; porta `UserAudienceReaderInterface` em `Shared/Application/User` para os módulos de ranking e grupos.

**Por que ficou fora:** sem menor usuário, não há projeção pública a filtrar.

**Como retomar:**

1. adicionar `nickname` ao `Profile` (auto-gerado, editável);
2. criar a política pura em `Shared/Domain` e a porta de leitura com adapter em `Identity`;
3. aplicar a política em toda projeção pública de ranking e grupo, com teste que garanta que nome, foto, altura, peso
   e cidade nunca vazam para menor.

### 3.5 Prêmio via responsável

**O que era:** prêmio externo liberado para 16–17 apenas com consentimento de escopo `PRIZE` e, quando exigido,
comprovação de tutela no resgate; bloqueado para menores de 16.

**Por que ficou fora:** a V1 exige CPF e maioridade no resgate (`RN20.6`), sem exceção.

**Como retomar:** criar `ConsentScope::PRIZE` por resgate (não um consentimento global) e um passo de comprovação de
tutela no fluxo de resgate, com retenção própria da evidência.

### 3.6 Acompanhantes menores em atividades

**O que era:** permitir que um adulto responsável levasse menores a atividades marcadas como
`acceptsAccompaniedMinors`, declarando quantidade por faixa etária (0–5, 6–11, 12–17) e assumindo a responsabilidade.

**Por que ficou fora:** a V1 não aceita menores na plataforma nem como acompanhantes; a declaração por si só não
protege ninguém e cria expectativa de que o sistema controla a presença de menores.

**Como retomar, se o produto decidir aceitar acompanhantes:**

1. introduzir `activities.max_participants` (hoje a atividade não tem limite de vagas);
2. criar `ActivityCompanionDeclaration` no agregado `Activity`: `(activity_id, responsible_user_id)` único, contagem
   por faixa, `declaration_version`, `declared_at`;
3. guardas: atividade aceita acompanhantes, responsável inscrito e maior de 18, limite por adulto, capacidade
   `inscrições + acompanhantes <= max_participants`;
4. acompanhante **não** é usuário, não gera XP, não entra em ranking e não conta no indicador mensal do grupo;
5. **não coletar nome, data de nascimento, CPF, e-mail ou foto do menor**. Se um local ou seguro exigir lista nominal,
   criar uma extensão opt-in por atividade, com retenção definida e base legal específica;
6. registrar a declaração como asserção do adulto, nunca como tutela verificada.

### 3.7 Retenção e expurgo

**O que era:** rotina `app:identity:purge-incomplete-accounts` para contas que nunca definiram data de nascimento,
com `INCOMPLETE_ACCOUNT_RETENTION_DAYS` configurável.

**Por que ficou fora:** ainda não implementado; hoje só a remoção imediata em `<18` existe.

**Como retomar:** comando de console + scheduler, com prazo definido em conjunto com o jurídico e registro em
`implementacao.md`.

## 4. Pré-requisitos para retomar

**Jurídico**

- texto do termo de consentimento do responsável, versionado e publicado;
- base legal e política de retenção para dados de menores;
- processo de comprovação de tutela para premiação;
- revisão do DPO/encarregado.

**Produto**

- decidir se o ranking de menor é público ou restrito ao grupo;
- decidir se acompanhante gera XP ou apenas ocupa capacidade;
- decidir se há idade mínima própria para cadastro (por exemplo, 13) mesmo com consentimento.

**Técnico**

- `AgeBand` e mudança de `AgePolicy::MINIMUM_AGE`;
- `guardian_consents` + e-mail transacional + rotas e rate limiter;
- `OnboardingStatus::AWAITING_GUARDIAN_CONSENT`;
- política de exposição e `UserAudienceReaderInterface`;
- tag OpenAPI `Consentimento` no grupo `Acesso`;
- job de retenção/expurgo.

## 5. Riscos ao retomar

- Reintroduzir "tutela verificada" no contrato da API. O contrato deve expor apenas `consentStatus` e `termVersion`.
- Liberar capacidade com termo vencido ou de versão anterior.
- Expor nome ou foto de menor por esquecimento em uma projeção nova.
- Persistir idade ou faixa em vez de derivar da data de nascimento.
- Coletar dados de menor "por precaução", sem finalidade operacional.

## 6. Referências

- [decisoes.md](decisoes.md) — `ADR-044`, `ADR-005`.
- [requisitos/levantamento-de-requisitos.md](requisitos/levantamento-de-requisitos.md) — `RF01`, `RF03`, `RNF01`,
  `RN20.6`.
- [autenticacao.md](autenticacao.md) — seção de data de nascimento e restrição de idade.
- [arquitetura.md](arquitetura.md) — modelo de `User` e porta de leitura cross-module.
