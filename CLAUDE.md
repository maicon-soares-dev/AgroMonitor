# AgroMonitor — Guia da migração do back-end para Laravel

Este arquivo orienta o Claude Code durante a migração da API do AgroMonitor de PHP puro
para Laravel e a inclusão dos requisitos do trabalho da disciplina. Leia inteiro antes de
começar qualquer etapa e atualize a seção **Status da migração** ao final de cada uma.

## 1. Contexto

- Sistema web de monitoramento de lavouras (milho e soja, expansível para qualquer cultura).
  O agrônomo/técnico percorre pontos de monitoramento numa área e registra pragas, doenças,
  plantas daninhas e condições climáticas encontradas.
- Sistema atual: `api/index.php` (API REST em PHP puro com PDO, ~1.660 linhas),
  `api/validators.php`, `index.html` (SPA em JavaScript puro, ~6.900 linhas, Leaflet para
  mapas) e `database/*.sql` (MySQL/MariaDB via XAMPP). A tabela `plantio_talhoes` só existe
  em `database/migracao_etapa5_talhoes.sql`.
- O sistema **ainda não está em produção**. Não há dados reais a migrar; o banco pode ser
  recriado do zero pelas migrations.
- Este projeto também é o trabalho final da disciplina Linguagem de Programação (FEMA, prazo
  de apresentação 27/11/2026). Os requisitos da nota estão na seção 3 e são obrigatórios.

## 2. Objetivo e decisões

Migrar **100% da API** para Laravel, manter o front atual e adicionar os requisitos do
professor.

| Camada | Tecnologia |
|---|---|
| Back-end | **Laravel** (última versão estável compatível com o PHP do XAMPP; confira `php -v`) |
| ORM | Eloquent + migrations + seeders |
| Segurança | **Laravel Sanctum** com tokens (Bearer) e perfis por middleware |
| Validação | Form Requests, com mensagens em português |
| Banco | MySQL/MariaDB do XAMPP |
| Front | O `index.html` atual (JavaScript puro + Leaflet), com os ajustes da seção 6 |
| Visual | Mantido como está |

**Decisão central: preservar o contrato da API.** A nova API responde nas mesmas rotas
(`/api/clientes`, `/api/areas/{id}`, `/api/relatorios/diario`...), com os mesmos nomes de
campo em snake_case e o mesmo formato de resposta (`{"sucesso": true, "dados": ...}`,
`{"sucesso": true, "id": ...}`, `{"sucesso": false, "erro": "..."}`). Assim o front só troca o
endereço da API. Antes de portar um endpoint, leia o trecho correspondente em
`legacy-php/api/index.php` e reproduza o mesmo JSON de saída. Mudar o contrato só quando
necessário, registrando no Status.

**Por que tokens e não cookies no Sanctum:** o app mobile e o modo offline planejados
precisam de autenticação por token.

## 3. Requisitos do professor (precisam estar visíveis e demonstráveis)

| Requisito | Como o AgroMonitor atende |
|---|---|
| CRUD de no mínimo 3 tabelas (sem contar o movimento) | Clientes, safras, culturas, variedades, pragas, adubos, áreas, talhões, plantios |
| Uso de CSS/framework CSS | CSS próprio do sistema atual |
| Tabela de movimento e movimentação | `monitoramentos` + `monitoramento_itens` (mestre-detalhe); também `aplicacoes` e `colheitas` |
| Validação de campos em formulários | Form Requests no back **e** mensagens ao lado de cada campo no front (seção 6) |
| Página principal e menu | Dashboard + menu lateral atuais; itens do menu escondidos conforme o perfil |
| Autenticação/autorização de páginas | Tela de login, token Sanctum, rotas protegidas com `auth:sanctum` e middleware de perfil |
| Geração de dados em tabelas | Geração automática dos pontos de monitoramento de uma área + seeders (culturas, pragas, usuário gestor) |
| Formulário de filtro nas páginas CRUD | **Toda** tela de cadastro com formulário de filtro (texto + campos relevantes); o filtro roda na API via Eloquent |
| Consulta multitabela com totalização | Dashboard e relatórios (joins com sum/count/avg) |
| Consulta de agrupamento | Nova tela "Relatórios gerenciais": ocorrências agrupadas por praga e por área, hectares por cliente |
| Consulta de pivot | Na mesma tela: pragas (linhas) × meses (colunas), com total por praga, filtrado por safra ou plantio, usando `SUM(CASE WHEN ...)` no query builder |
| Uso do GitHub durante o desenvolvimento | Commits pequenos ao fim de cada etapa (seção 9) |

Consultas de relatório usam Eloquent/Query Builder, não SQL cru em string, para que o grupo
consiga explicar na entrevista escrita.

## 4. Estrutura do repositório

```
AgroMonitor/
├── backend/                  ← projeto Laravel
│   ├── app/
│   │   ├── Http/Controllers/Api/   ← um controller por recurso
│   │   ├── Http/Requests/          ← Form Requests (validação)
│   │   ├── Http/Middleware/        ← middleware de perfil
│   │   ├── Models/                 ← models Eloquent com relacionamentos
│   │   └── Services/               ← regras de negócio (índice da praga, talhões, relatórios)
│   ├── database/migrations, seeders
│   └── routes/api.php
├── frontend/                 ← index.html, assets/ e scripts do front atual
├── legacy-php/               ← api/ e database/ antigos, só como referência
└── CLAUDE.md
```

Em desenvolvimento: API com `php artisan serve` (http://localhost:8000) e front servido
pelo Apache do XAMPP. CORS em `config/cors.php` liberado só para a origem do front.

## 5. Convenções do back-end

- Tabelas e colunas com os **mesmos nomes do banco atual** (snake_case, em português). Nos
  models, `$table` explícito quando o plural do Laravel não bater (ex.: `areas`, `pragas`).
- Timestamps: o banco atual usa `criado_em`; use `const CREATED_AT = 'criado_em'` e adicione
  `atualizado_em` (`const UPDATED_AT`) em todas as tabelas.
- **IDs:** `monitoramentos`, `monitoramento_itens` e `monitoramento_fotos` usam UUID
  (`HasUuids`), pensando na sincronização offline futura. As demais seguem com
  auto-incremento. Verifique no front se algum código trata esses ids como número
  (`parseInt`, `Number`, comparação com `===` contra número) e ajuste.
- FKs e `ON DELETE` replicando os `.sql` atuais.
- Controllers magros: recebem, validam via Form Request, chamam o model ou service e
  respondem. Regras de negócio em `Services/`.
- Usuários: tabela `users` do Laravel adaptada com `login` (único), `perfil`
  (`gestor | agronomo | tecnico`) e `ativo`. Login é por `login` + senha, não por e-mail.
- Fotos: `Storage::disk('public')` + `php artisan storage:link`; a API devolve a URL completa.
- Nada de segredo versionado: só o `.env.example` vai para o Git.
- Erros de validação no formato `{"sucesso": false, "erro": "...", "erros": {"campo": ["mensagem"]}}`,
  para o front conseguir mostrar a mensagem ao lado de cada campo.

## 6. Ajustes no front (`frontend/index.html`)

- `const API` passa a apontar para a URL da API Laravel, definida num único lugar.
- Criar uma função `apiFetch()` que adiciona `Authorization: Bearer <token>` e trata 401
  (volta para o login). Substituir as chamadas `fetch(\`${API}...` por `apiFetch(...)`.
- Tela de login, botão de sair, nome e perfil do usuário no topo, menu filtrado por perfil.
- Tela de gestão de usuários (só gestor).
- Formulário de filtro em todas as telas de cadastro (clientes, safras, culturas, variedades,
  pragas, adubos, áreas, plantios, aplicações, colheitas).
- Mensagens de validação ao lado de cada campo, usando o `erros` devolvido pela API.
- Nova tela "Relatórios gerenciais" com o agrupamento e o pivot.
- Ajustar a URL das fotos.
- Não reescrever nem reorganizar o restante do front: mudanças mínimas e localizadas.

## 7. Inventário da API atual (paridade obrigatória)

| Recurso | Operações | Observações |
|---|---|---|
| dashboard | GET | KPIs (áreas, hectares, plantios, itens, críticos), hectares por cliente, plantios por mês, top pragas; parâmetro `periodo` |
| clientes, culturas, variedades, adubos, pragas, safras | CRUD | Variedades e pragas filtradas por cultura |
| areas | CRUD | Geometria GeoJSON, hectares/alqueires, talhões na criação, contagem de talhões, filtro por safra (áreas com plantio na safra) |
| talhoes | CRUD | Divisão física fixa da área; cor, hectares, geometria |
| plantios | CRUD | Área + safra + cultura + variedade; ou variedade por talhão (`plantio_talhoes`) |
| pontos | CRUD | Pontos de monitoramento da área, numerados |
| monitoramentos | CRUD | Por ponto e plantio; cálculo milho (% plantas) e soja (pragas/m²); nível de controle; itens |
| monitoramento-fotos | POST, PUT, DELETE | Upload; foto "favorita" para o relatório |
| resumo-monitoramento | GET | Resumo por plantio com quantidades por nível de controle |
| relatorios/dias, /diario, /safra, /comparativo, /cliente | GET | Relatório do dia (média dos pontos, régua fenológica, fotos favoritas), resumo da safra, comparativo, visão por cliente |
| aplicacoes | CRUD | Defensivo/adubo/corretivo, ligado a área/talhão/plantio/monitoramento |
| colheitas | CRUD | Data, produtividade (sc/ha), umidade, por plantio |

Funções a portar para `Services/`: `indicePraga`, `sqlVariedadeNome`, `inserirTalhao`,
`salvarTalhoesPlantio`, `talhoesDoPlantio`, `resumoMonitoramentosPlantio`,
`plantiosComResumo`. As validações de `api/validators.php` viram Form Requests.

## 8. Perfis de acesso (proposta, confirmar com o Maicon)

| Perfil | Pode |
|---|---|
| Gestor | Tudo, inclusive gestão de usuários |
| Agrônomo | Cadastros agronômicos, áreas/talhões, plantios, monitoramentos, aplicações, colheitas, relatórios |
| Técnico | Lançar e editar monitoramentos; consultar áreas, plantios e relatórios |

## 9. Etapas (uma ou duas por sessão, commits ao fim de cada uma)

0. **Preparação:** criar a branch `migracao-laravel`; mover `api/` e `database/` para
   `legacy-php/`; mover `index.html`, `assets/` e os HTML de teste para `frontend/`;
   ajustar o `.gitignore` (vendor, node_modules, .env, storage).
1. **Base do Laravel:** criar o projeto em `backend/`, configurar `.env` para o MySQL do
   XAMPP, migrations de todas as tabelas, models com relacionamentos, seeders. Rodar
   `php artisan migrate --seed` sem erros.
2. **Autenticação:** Sanctum, login/logout/me, middleware de perfil, CRUD de usuários
   (gestor). No front: `apiFetch`, tela de login, sair, menu por perfil, tela de usuários.
3. **Cadastros simples:** clientes, safras, culturas, variedades, pragas, adubos, com Form
   Requests, filtros na API e no front, e mensagens por campo.
4. **Áreas, talhões e pontos.**
5. **Plantios** (variedade única ou por talhão).
6. **Monitoramentos, itens e fotos.**
7. **Aplicações e colheitas** (com filtros).
8. **Dashboard, resumo e relatórios** do sistema atual.
9. **Relatórios gerenciais:** agrupamento e pivot (back + tela nova).
10. **Fechamento:** testar todas as telas, README com o passo a passo para rodar, remover
    `legacy-php/` (só com autorização do Maicon), abrir o pull request para a `main`.

## 10. Regras de trabalho

- Nunca commitar direto na `main`. Trabalhar na `migracao-laravel`.
- Antes de cada commit: `php artisan migrate:fresh --seed` sem erros e as rotas da etapa
  testadas (`php artisan route:list` + chamadas reais); telas da etapa testadas no navegador.
- Commits em português, no padrão Conventional Commits
  (`feat: cadastro de clientes com filtro`, `fix: ...`, `chore: ...`).
- Mudanças mínimas no front; não refatorar o que não faz parte da etapa.
- Dúvida sobre regra de negócio: ler o PHP antigo; se continuar ambíguo, perguntar ao
  Maicon em vez de inventar.
- Ao terminar uma etapa, atualizar o Status abaixo.

## 11. Status da migração

| Etapa | Situação | Observações |
|---|---|---|
| 0. Preparação | Concluída | Branch `migracao-laravel`. `api/`, `database/` e `uploads/` (fotos antigas, fora do Git) movidos para `legacy-php/`; `index.html`, `assets/` e HTML de teste em `frontend/`. O front ainda aponta para `api/index.php` e fica sem API até a etapa 2 |
| 1. Base do Laravel | Concluída | Laravel 12.70 (PHP 8.2.12 do XAMPP; o 13 exige 8.3). Banco **`agromonitor_laravel`** (o `agromonitor` antigo tem dados de teste e foi preservado como referência). IDs auto-incremento são `BIGINT UNSIGNED`. `users` com `nome`, `login`, `email` (opcional), `password`, `perfil`, `ativo`; FKs que apontavam para `usuarios` agora apontam para `users`. `plantio_talhoes` ganhou `criado_em`/`atualizado_em`; `relatorios` usa `gerado_em` como `CREATED_AT`. Models herdam de `App\Models\Modelo` (timestamps em português), sem casts de data/decimal para não mudar o JSON do contrato. Gestor do seeder: `GESTOR_LOGIN`/`GESTOR_SENHA` no `.env` (sem senha, o seeder gera uma e mostra no console). MySQL do XAMPP precisa estar ligado |
| 2. Autenticação | Concluída | Sanctum 4 só com tokens Bearer (`POST /api/login` com `login` + `senha` → `dados.token` e `dados.usuario`; `POST /api/logout`; `GET /api/me`). Login com `throttle:10,1`; credencial errada ou usuário inativo → 422 com `erros.login`. Middleware `perfil:gestor,agronomo` (alias em `bootstrap/app.php`); `bootstrap/app.php` também padroniza todos os erros em `api/*` (`sucesso/erro/erros`; 401, 403, 404, 422, 500). Base `Controller` com `dados()`, `criado()`, `ok()`, `erro()` (criação responde 200 como a API antiga). `Modelo` e `User` serializam datas como `Y-m-d H:i:s`. Mensagens em pt_BR via `lucascudo/laravel-pt-br-localization` (arquivos copiados para `lang/`). CRUD `/api/usuarios` (só gestor), filtros `busca`, `perfil`, `ativo` — **convenção: o filtro de texto das telas CRUD se chama `busca`**. Gestor não pode se excluir/rebaixar/desativar; desativar revoga os tokens. `.env`: `FRONTEND_URL` (CORS) e `SANCTUM_EXPIRATION` (minutos, padrão 10080). `personal_access_tokens` mantém `created_at/updated_at` (tabela do Sanctum). **Front:** `const API = 'http://localhost:8000/api'`, `apiFetch()` (Bearer + 401 → login), token em `localStorage` (`agromonitor_token`), helpers `mostrarErrosCampos(form, erros)`/`limparErrosCampos(form)` usando `data-campo` nos inputs — reusar nas próximas telas. Menu por `data-perfis` no `.nav-item` (sem atributo = todos): técnico vê Dashboard, Áreas, Mapa, Histórico Plantio, Monitoramento, Registrar Monitoramento e Relatório; agrônomo vê tudo menos Usuários e Configurações (identidade visual); **a matriz da seção 8 foi aplicada como proposta — falta o Maicon confirmar** (em especial clientes/safras para agrônomo). Ocultar menu é só visual: as rotas de cada recurso devem receber `perfil:` nas próximas etapas. Testado com curl e com Chrome headless (Playwright): login, erros por campo, sessão ao recarregar, sair, menu do técnico, token inválido |
| 3. Cadastros simples | Concluída | Controllers + Form Requests para clientes, safras, culturas, variedades, adubos e pragas. Contrato mantido: listagens só com ativos (safras: todas, da mais recente), `pragas` com `cultura_nome` (leftJoin) e `cultura_id` incluindo os itens de "qualquer cultura", DELETE de cliente = `ativo = 0`, demais são exclusão física. Padrões da API antiga mantidos no controller: safra sem mês → 1/12 e `ativa` → 1; praga sem tipo/risco → `praga`/`medio`. **Mudanças de contrato:** texto vazio agora é gravado como `null`, não `''` (middleware `ConvertEmptyStringsToNull`); erros de validação vêm como 422 com `erros`; exclusão bloqueada por FK RESTRICT (ex.: safra/cultura com plantio) → 409 com mensagem amigável (handler de `QueryException` 1451 em `bootstrap/app.php`), antes era 500. Validações novas: nomes únicos (cultura, safra, adubo, praga; variedade única por cultura) e fim da safra depois do início. Filtros (query string): `busca` em todos; `cultura_id` (variedades, pragas); `tipo`, `nivel_risco` (pragas); `ano`, `ativa` (safras). Rotas: `index/show` para qualquer perfil (o técnico usa as listas no monitoramento); `store/update/destroy` com `perfil:gestor,agronomo`. **Front:** classe `.filtro-form` + helper `queryFiltros({param: idDoCampo})`; `data-campo` aceita vários campos da API (`data-campo="mes_inicio ano_inicio"`); formulários com `novalidate` para a mensagem vir da API; `carregarPragas()` só aplica os filtros quando a tela de Pragas está aberta (o Registrar Monitoramento precisa do catálogo inteiro). Testado: 27 checagens de API e 22 no Chrome headless, mais a regressão da etapa 2 |
| 4. Áreas, talhões e pontos | Concluída | `AreaService` (criar/atualizar/`gerarPontos`) e `TalhaoService::inserir` (antigo `inserirTalhao`). Criar área gera os pontos 1..`qtd_pontos` (usa `pontos_coords` [[lat,lng]] do front) e os `talhoes` enviados, em transação; editar só cria os pontos que faltam (nunca apaga) e só troca a `geometria` se a chave vier no corpo. `geometria` gravada/devolvida como **texto JSON** (como antes; `properties: {}` volta como `[]`, igual à API antiga). Áreas: scope `Area::comResumo()` (`nome_cliente`, `qtd_talhoes`), ordem `criado_em` desc; filtros `busca` (área ou cliente), `cliente_id`, `safra_id` (áreas com plantio na safra). Talhões: `?area_id=&plantio_id=` devolve `plantio_variedade_id`, `plantio_variedade_nome`, `no_plantio` (0/1); PUT parcial (`nome`, `cor_hex`, `hectares`, `geometria`; vazio → 400 "Nada para atualizar"). Pontos: `numero_ponto` único por área, `talhao_id` precisa ser da mesma área, lat/lng com faixa válida; PUT parcial. `area_id` não pode ser trocado na edição de talhão/ponto. Rotas: leitura para todos, alteração `perfil:gestor,agronomo` (o técnico vê o Mapa, mas recebe 403 ao arrastar ponto/editar talhão — os botões do mapa ainda não são escondidos por perfil). **Front:** tela de Áreas com `.filtro-form` (busca, cliente, safra) no lugar da busca local; mensagens por campo em `#formArea` e na Nova Área; `data-perfis` agora vale para qualquer elemento (botão "+ Nova Área" some para o técnico). Testado: 30 checagens de API e 12 no Chrome headless (inclui criação pela Nova Área e o mapa), mais a regressão das etapas 2 e 3 |
| 5. Plantios | Concluída | `PlantioService`: `criar`/`atualizar` (transação), `salvarTalhoes` (antigo `salvarTalhoesPlantio`; substitui a lista, só mexe se `talhoes` vier no corpo), `talhoesDoPlantio` (para os relatórios) e `PlantioService::sqlVariedadeNome()` (expressão SQL com `GROUP_CONCAT`, reaproveitar nos relatórios). Scope `Plantio::comResumo()`: `cultura_nome`, `safra_nome`, `adubo_nome`, `variedade_nome` e **`area_nome` (campo novo)**; a resposta traz `talhoes: [{plantio_id, talhao_id, variedade_id}]` como antes; ordem `data_plantio` desc. Filtros `area_id`, `safra_id`, `cultura_id`, `busca` (área, cultura, variedade — inclusive dos talhões). `GET /plantios/{id}` novo. Validações novas: variedade (única ou do talhão) precisa ser da cultura do plantio, talhões precisam ser da área, `condicao_plantio`/`condicao_solo` dentro das opções da tela, `data_plantio` em `Y-m-d`. Vazios → `apos_chuva`/`boa` como antes. Leitura para todos; alteração `perfil:gestor,agronomo`. **Front:** filtro no Histórico (só vale com a tela aberta — o dashboard usa a mesma função), mensagens por campo em `#formPlantio`; erros de `talhoes.*` aparecem em toast. **Ambiente:** `const API` e `APP_URL` agora usam `127.0.0.1:8000` (no Windows `localhost` tenta IPv6 antes e perdia ~0,2 s por chamada); `carregarSelectAreas`/`carregarSelectCulturas` preservam a opção escolhida ao recarregar (com a API mais lenta que a antiga, a escolha era apagada). Cada chamada ainda leva ~0,2 s porque o PHP do XAMPP está sem OPcache (não alterado). **Pendente da etapa 8:** a tela de detalhe do plantio (aberta após salvar) usa `/relatorios/safra` e mostra "rota não encontrada" até lá. Testado: 24 checagens de API e 11 no Chrome headless, mais a regressão das etapas 2 a 4 |
| 6. Monitoramentos, itens e fotos | Concluída | `MonitoramentoService`: `criar`/`atualizar` (transação), `salvarItens` (substitui a lista, só mexe se `itens` vier no corpo; item sem nome é ignorado, tipo vazio → `praga`), `excluir`, `adicionarFoto`/`excluirFoto` e **`MonitoramentoService::indicePraga()`** (antigo `indicePraga`, reaproveitar nos relatórios; constantes `LIMITE_MILHO` 10% e `LIMITE_SOJA` 3/m²). Scope `Monitoramento::comResumo()` (`area_id`, `numero_ponto`, `area_nome`, `data_plantio`, `safra_id`, `safra_nome`, `cultura_nome`); resposta com `itens` e `fotos` (cada foto com `url`). Filtros `area_id`, `plantio_id`, `safra_id` (como antes) e **novos** `nivel_controle`, `data_inicio`, `data_fim`; ordem data desc + número do ponto. `GET /monitoramentos/{id}` mantém o contrato antigo: `dados` é **lista** com um item. **Mudança de regra:** para milho e soja o back recalcula `resultado_final`/`unidade_resultado`/`nivel_controle` com a mesma fórmula da tela (milho praga÷avaliadas×100, 1 casa; soja pragas÷(metros/2), 1 casa) e ignora o que o front mandar; outras culturas gravam o que vier. Validações novas: ponto precisa ser da área do plantio, plantas com praga ≤ avaliadas, `nivel_controle` (`normal/atencao/critico`), tipo (`praga/doenca/planta_daninha/clima`) e severidade (`leve/moderada/severa`) do item, data `Y-m-d`. Fotos: `storage/app/public/monitoramento_fotos` (`php artisan storage:link` — `public/storage` fora do Git), URL completa via `Storage::disk('public')->url()` (accessor `url` em `MonitoramentoFoto`); upload valida imagem JPG/PNG/WEBP até 8 MB e monitoramento existente (agora 422 com `erros.foto`/`erros.monitoramento_id`, antes 400/404); excluir foto ou monitoramento apaga os arquivos. `GET /resumo-monitoramento` portado já nesta etapa (é a lista principal da tela Monitoramento). Rotas: todos os perfis consultam, lançam e editam monitoramentos e fotos; `DELETE /monitoramentos/{id}` só `perfil:gestor,agronomo`. **Front:** ids UUID entre aspas nos `onclick` (lista, detalhe, fotos); `#formMonitoramento` com `novalidate` e `data-campo` (contadores de milho/soja marcados na `.counter-group`); erros de `itens.*` em toast; botão Excluir do monitoramento some para o técnico (lista e detalhe). Fotos antigas em `legacy-php/uploads` não foram migradas (sem dados reais). Testado: 30 checagens de API e 15 no Chrome headless (inclui upload, favoritar/legenda, edição e exclusão por perfil), mais a regressão das etapas 2 a 5 |
| 7. Aplicações e colheitas | Concluída | Controllers + Form Requests (`AplicacaoController`, `ColheitaController`), scopes `Aplicacao::comResumo()` (`area_nome`, `cliente_nome`, `cultura_nome`, `safra_nome`) e `Colheita::comResumo()` (`data_plantio`, `area_id`, `area_nome`, `hectares`, `cliente_nome`, `cultura_nome`, `safra_nome`, `variedade_nome` via `PlantioService::sqlVariedadeNome('pl', 'v')`), mesmo JSON da API antiga; ordem por data desc. **Aplicações:** `plantio_id`, `data_aplicacao` e `produto` obrigatórios; a `area_id` vem do plantio (como antes, também na edição); `tipo_produto` em `defensivo/adubo/corretivo` (vazio → `null`); aceita também `talhao_id` (da área do plantio) e `monitoramento_id` (do plantio), opcionais — o front ainda não envia; **novo:** `usuario_id` = usuário logado na criação. Filtros `area_id` (antigo) e novos `plantio_id`, `safra_id`, `cliente_id`, `tipo_produto`, `data_inicio`, `data_fim`, `busca` (produto, área, cliente, observações). **Colheitas:** uma por plantio (`Rule::unique` ignorando a própria), data ≥ data do plantio, produtividade > 0, umidade 0–100, com as mesmas mensagens da API antiga — agora 422 com `erros` por campo (antes 400). Filtros `area_id` (antigo) e novos `safra_id`, `cultura_id`, `cliente_id`, `busca` (área, cliente, variedade — inclusive dos talhões). `GET /aplicacoes/{id}` e `/colheitas/{id}` novos (objeto). Rotas inteiras (inclusive leitura) só `perfil:gestor,agronomo`, seguindo a matriz da seção 8 (nenhuma tela do técnico usa essas rotas). **Front:** `.filtro-form` nas duas telas (aplicações: área, safra, tipo, de/até, busca; colheitas: área, safra, cultura, busca); `#formAplicacao`/`#formColheita` com `novalidate` e `data-campo`; o bloqueio de "plantio já colhido" no select agora consulta `/colheitas?area_id=` (antes usava a lista da tela, que pode estar filtrada). Testado: 37 checagens de API e 19 no Chrome headless, mais a regressão das etapas 2 a 6 |
| 8. Dashboard e relatórios | Pendente | |
| 9. Relatórios gerenciais | Pendente | |
| 10. Fechamento | Pendente | |
