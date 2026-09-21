---

### Arquivo 2: `docs/requisitos/SIGAME_Levantamento_e_Especificacao_Inicial.md`

```markdown
# SIGAME - Levantamento e Especificação Inicial
**Documento de Requisitos para anexar no Trello — Versão 1**

---

## 1. Visão do Projeto
O **SIGAME** é uma plataforma web voltada ao incentivo à prática de atividades físicas em grupo. A proposta central é oferecer um canal próprio para que pessoas encontrem outras com interesses e disponibilidades compatíveis, formem pequenos grupos, organizem atividades e acompanhem sua evolução.

O projeto prioriza socialização, inclusão, segurança, criação de laços e motivação por gamificação. As modalidades iniciais são **caminhada, corrida e ciclismo**. O usuário poderá praticar mais de uma modalidade e alterar sua modalidade principal sem perder histórico ou progresso.

---

## 2. Problema a Resolver
Redes sociais e grupos de mensagens não foram projetados para localizar grupos esportivos compatíveis, acompanhar evolução, organizar atividades, controlar presença ou oferecer mecanismos próprios de incentivo. O SIGAME busca centralizar essas necessidades em um ambiente específico para prática esportiva coletiva.

---

## 3. Público e Inclusão
O sistema deve acolher tanto pessoas já ativas quanto iniciantes, inclusive usuários tímidos, sedentários ou com receio de praticar atividades sozinhos. Informações como peso e altura serão opcionais e não poderão impedir o acesso às funcionalidades sociais.

---

## 4. Cadastro e Onboarding
- Todo usuário inicia no **Nível 1** e realiza um onboarding simples.
- Ao concluir os requisitos cadastrais mínimos, alcança o **Nível 2**.
- A partir do **Nível 2**, poderá solicitar entrada e criar grupos.
- CPF será único por conta (sem validação governamental na V1).
- Foto de perfil não será obrigatória.
- Peso e altura serão opcionais.
- O usuário poderá selecionar caminhada, corrida e/ou ciclismo e definir uma modalidade principal.

---

## 5. Grupos
- O grupo permanece em formação até atingir no mínimo **3 membros aprovados**.
- O limite inicial será de **5 membros**, com possibilidade de ampliação futura.
- O criador torna-se **administrador** do grupo.
- Poderá existir **supervisor** com poderes operacionais (recomendado, mas não obrigatório).
- A entrada depende de solicitação do usuário e aprovação do administrador.
- Limite inicial de até **3 grupos por usuário**.
- O sistema deverá impedir conflitos de horários entre atividades nas quais o usuário esteja inscrito.
- O grupo poderá continuar existindo e organizar novas atividades após o término de um evento.
- O usuário poderá sair de um grupo e procurar outros.

---

## 6. Comunicação do Grupo
Não haverá chat próprio na V1. O administrador poderá cadastrar um link externo de convite (ex: grupo do WhatsApp). Esse link **somente será exibido para membros aprovados**, evitando exposição pública de telefone pessoal.

---

## 7. Atividades e Presença
- **Campos mínimos da atividade:** Data, horário, ponto de partida/encontro, ponto final ou trajeto e distância estimada.
- Administrador e supervisor poderão criar, remarcar, cancelar, iniciar e finalizar atividades conforme permissões.
- Mudanças relevantes deverão gerar avisos internos.
- O participante poderá realizar **check-in individual**.
- Administrador ou supervisor poderá confirmar a presença.
- **Somente participação validada gera XP de atividade.**
- Não haverá validação obrigatória por GPS na V1.
- Atividades não devidamente iniciadas/finalizadas dentro da janela operacional poderão ser invalidadas.

---

## 8. Gamificação, XP e Níveis
O SIGAME possuirá **10 níveis**:
- **Níveis 1 a 2:** Preparação e onboarding (Nível 2 libera a camada social).
- O XP considerará ofensiva de acesso, participação validada em grupos e atividades realizadas.
- O XP de participação em grupo será visível.

### Recompensas
- Recompensas principais previstas nos **níveis 5 (marco intermediário), 8 e 10 (marco máximo)**.
- Recompensas podem incluir benefícios de patrocinadores, vouchers e elementos internos de gamificação.
- Administradores de grupos poderão cadastrar recompensas internas para atividades específicas (inclusive surpresa), mantendo o prêmio registrado no sistema.

---

## 9. Deleveling por Inatividade
- **Até 7 dias sem atividade válida:** Tolerância (sem perda de XP).
- **Após 7 dias:** Perda de **2% da barra do nível atual a cada 2 dias** sem atividade válida.
- **Perda de nível:** Retorna ao nível anterior com **98% da barra**.
- **Piso de deleveling:** Nível 2 (o usuário nunca cai para o Nível 1).
- O usuário **não perde** grupos já conquistados ou recompensas já resgatadas ao sofrer deleveling.

---

## 10. Segurança e Moderação
- Administrador do grupo poderá rejeitar entrada e remover membros (registrando o motivo).
- Usuários poderão denunciar e bloquear outros usuários.
- A suspensão global da conta é exclusiva da administração do SIGAME.
- Dados sensíveis não serão exibidos ao administrador do grupo.
- Identificação social priorizará nome, inicial do sobrenome, sexo e idade/faixa etária.

---

## 11. Patrocínios e Vouchers
O patrocínio da V1 será associado ao **grupo**, e não ao atleta individual. O SIGAME manterá um indicador interno mensal de desempenho dos grupos (atividades válidas, frequência e participação). Esse ranking **não será público**.

- A administração do SIGAME decide o vínculo entre patrocinador e grupo.
- O patrocinador poderá cadastrar proposta de benefício (sujeita à aprovação).
- Vouchers serão **individuais, intransferíveis** e não comercializáveis.
- Cada voucher terá código único e controle de estado (disponível, utilizado, etc.).

---

## 12. Escopo Tecnológico Inicial
- Aplicação Web (mobile first + responsividade).
- Nuxt 4 + Vue.
- API em Symfony.
- PostgreSQL.

---

## 13. Fora do Escopo Inicial (Out of Scope)
- Aplicativo mobile nativo.
- Chat interno próprio.
- Rastreamento GPS obrigatório (estilo Strava).
- Integração financeira (Pix, comissão e repasse).
