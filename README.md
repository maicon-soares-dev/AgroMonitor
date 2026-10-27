# AgroMonitor

Sistema web de monitoramento de lavouras (milho e soja, expansível para outras culturas).
O agrônomo ou técnico percorre os pontos de monitoramento de uma área e registra pragas,
doenças, plantas daninhas e condições climáticas. O sistema calcula o índice de cada praga,
compara com o nível de controle e gera relatórios para o cliente.

Trabalho final da disciplina **Linguagem de Programação** (FEMA).

| Camada | Tecnologia |
|---|---|
| Back-end | Laravel 12 (PHP 8.2), API REST em `backend/` |
| Banco | MySQL/MariaDB (XAMPP), migrations e seeders do Laravel |
| Autenticação | Laravel Sanctum (token Bearer) e perfis por middleware |
| Front-end | HTML, CSS e JavaScript puro em `frontend/index.html`, mapas com Leaflet |

## Estrutura

```
agromonitor/
├── backend/      API Laravel (controllers, Form Requests, models, services, migrations, seeders)
├── frontend/     index.html (SPA) e assets/
├── legacy-php/   API PHP antiga, só como referência (será removida)
└── CLAUDE.md     guia e histórico da migração
```

## Como rodar (Windows + XAMPP)

### 1. Pré-requisitos

- [XAMPP](https://www.apachefriends.org/) com **PHP 8.2 ou mais novo**: confira com `php -v`.
- [Composer](https://getcomposer.org/).
- Git.

### 2. Baixar o projeto

Clone dentro do `htdocs` do XAMPP, para o Apache servir o front:

```bash
cd C:\xampp\htdocs
git clone https://github.com/maicon-soares-dev/AgroMonitor.git agromonitor
```

### 3. Ligar o banco e criar a base

1. No painel do XAMPP, ligue o **Apache** e o **MySQL**.
2. Crie o banco `agromonitor_laravel` com o phpMyAdmin (`http://localhost/phpmyadmin`) ou pelo terminal:

```bash
C:\xampp\mysql\bin\mysql -u root -e "CREATE DATABASE agromonitor_laravel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

### 4. Configurar e subir a API

```bash
cd C:\xampp\htdocs\agromonitor\backend
composer install
copy .env.example .env
php artisan key:generate
```

No `.env`, defina a senha do usuário gestor criado pelo seeder:

```
GESTOR_LOGIN=gestor
GESTOR_SENHA=uma-senha-forte
```

Se `GESTOR_SENHA` ficar vazio, o seeder gera uma senha e mostra no terminal.

Crie as tabelas com os dados iniciais (culturas, variedades, pragas, adubos, safra e gestor),
crie o link das fotos e suba a API:

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

Deixe esse terminal aberto. A API responde em `http://127.0.0.1:8000/api`.

### 5. Abrir o sistema

Acesse **http://localhost/agromonitor/frontend/** e entre com o login e a senha do gestor.

> Use `localhost` no endereço do front. A API só aceita chamadas da origem definida em
> `FRONTEND_URL` no `.env` (padrão `http://localhost`). Se abrir o front por outro endereço,
> acrescente-o ali, separado por vírgula.

### Recriar o banco do zero

```bash
php artisan migrate:fresh --seed
```

Isso apaga todos os dados e recria as tabelas com os dados iniciais.

## Perfis de acesso

| Perfil | Pode |
|---|---|
| Gestor | Tudo, inclusive gestão de usuários e configurações do relatório |
| Agrônomo | Cadastros, áreas e talhões, plantios, monitoramentos, aplicações, colheitas e relatórios (inclusive os gerenciais) |
| Técnico | Lançar e editar monitoramentos; consultar áreas, plantios, mapa e relatórios |

O menu esconde o que o perfil não pode usar, e a API bloqueia a rota com 403.

## Onde está cada requisito da disciplina

| Requisito | Onde ver |
|---|---|
| CRUD de 3 ou mais tabelas | Clientes, safras, culturas, variedades, pragas, adubos, áreas, talhões, pontos, plantios, usuários: `backend/app/Http/Controllers/Api/` |
| CSS | CSS próprio no `frontend/index.html` |
| Tabela de movimento (mestre-detalhe) | `monitoramentos` + `monitoramento_itens` + `monitoramento_fotos` (`MonitoramentoController`, `MonitoramentoService`); também `aplicacoes` e `colheitas` |
| Validação de formulários | Form Requests em `backend/app/Http/Requests/` (mensagens em português) e mensagem ao lado de cada campo no front (`mostrarErrosCampos`) |
| Página principal e menu | Dashboard e menu lateral, filtrado pelo perfil |
| Autenticação e autorização | Tela de login, Sanctum (`AuthController`), `auth:sanctum` e middleware `perfil:` (`VerificaPerfil`) em `backend/routes/api.php` |
| Geração de dados em tabelas | Pontos de monitoramento gerados ao criar a área (`AreaService::gerarPontos`) e seeders em `backend/database/seeders/` |
| Formulário de filtro nas telas CRUD | Filtros em todas as telas de cadastro; a filtragem roda na API (parâmetro `busca` e campos de cada tela) |
| Consulta multitabela com totalização | Dashboard e relatórios (`RelatorioService`), resumo do monitoramento (`ResumoMonitoramentoController`) |
| Consulta de agrupamento | Tela **Relatórios Gerenciais**: ocorrências por praga e por área, hectares por cliente (`RelatorioGerencialService`) |
| Consulta de pivot | Mesma tela: pragas × meses da safra com `SUM(CASE WHEN ...)` no Query Builder (`RelatorioGerencialService::pivot`) |
| Uso do GitHub | Commits por etapa na branch `migracao-laravel` |

## API

Todas as rotas ficam em `/api`, exigem `Authorization: Bearer <token>` (menos o login) e respondem no formato
`{"sucesso": true, "dados": ...}` ou `{"sucesso": false, "erro": "...", "erros": {"campo": ["mensagem"]}}`.

| Recurso | Rotas |
|---|---|
| Autenticação | `POST /login`, `POST /logout`, `GET /me` |
| Cadastros | `/clientes`, `/safras`, `/culturas`, `/variedades`, `/pragas`, `/adubos`, `/usuarios` |
| Áreas | `/areas`, `/talhoes`, `/pontos` |
| Plantio e movimento | `/plantios`, `/monitoramentos`, `/monitoramento-fotos`, `/aplicacoes`, `/colheitas` |
| Consultas | `GET /dashboard`, `GET /resumo-monitoramento`, `GET /relatorios/{dias,diario,safra,comparativo,cliente}` |
| Relatórios gerenciais | `GET /relatorios-gerenciais/agrupamentos`, `GET /relatorios-gerenciais/pivot` |

A lista completa sai em `php artisan route:list --path=api`.

## Problemas comuns

- **"Não foi possível conectar à API"**: o `php artisan serve` não está rodando, ou o MySQL está desligado.
- **Erro de CORS no console do navegador**: o endereço do front não está em `FRONTEND_URL`.
- **Fotos não aparecem**: falta rodar `php artisan storage:link`.
- **`composer install` reclama da versão do PHP**: o projeto precisa de PHP 8.2 ou mais novo.
