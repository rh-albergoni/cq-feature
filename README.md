# CqFeature

Gerador de **scaffold DDD** (Module → Domain → Feature) instalável como pacote Composer
para projetos Laravel da Cq. É a evolução da biblioteca interna `FeatureMaker` do
`cq/api`, agora reutilizável e **100% configurável** (caminhos, namespaces, rotas,
comando e stubs) via `config/cqfeature.php`, no padrão dos demais pacotes `Cq*`.

---

## Documentação

**[Abrir documentação completa no navegador →](https://cqplace.github.io/CqFeature/)**

Site estático (GitHub Pages) com API, exemplos, configuração e integração Laravel.
Fonte: [`docs/index.html`](docs/index.html).

---

## Instalação

Como o pacote é distribuído por VCS privado (`CqPlace/CqFeature`), adicione o
repositório ao `composer.json` do projeto consumidor:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/CqPlace/CqFeature.git"
        }
    ]
}
```

Em seguida, instale via Composer:

```bash
composer require cq/cqfeature
```

O **auto-discovery** do Laravel registra o `Cq\CqFeature\Laravel\CqFeatureServiceProvider`
automaticamente (declarado em `extra.laravel.providers`).

---

## Compatibilidade

- PHP `^8.1`
- Laravel 10 / 11 / 12 (`illuminate/support` e `illuminate/console` `^10|^11|^12`)

---

## Uso

Gere um domínio CRUD completo com rotas:

```bash
php artisan feature Financial Finance --features=crud --register-routes
```

O nome do comando (`feature`) vem de `config('cqfeature.command.name')`. Argumentos:
`module`, `domain`; opções: `--features=`, `--force`, `--register-routes`,
`--aggregate-of=`.

Para customizar caminhos/namespaces/rotas/comando, publique a config:

```bash
php artisan vendor:publish --tag=cqfeature-config
```

Para sobrescrever os templates por projeto (o stub publicado tem precedência sobre o
do pacote), publique os stubs:

```bash
php artisan vendor:publish --tag=cqfeature-stubs
```

O passo a passo completo está no tutorial [`docs/index.html`](docs/index.html).

---

## Desenvolvimento

```bash
composer install
composer test
```

A suíte de testes usa **PHPUnit puro** (sem Orchestra Testbench), dividida em duas suítes:

- `Unit` — `tests/Unit`
- `Features` — `tests/Features` (E2E + equivalência)

### Equivalência por snapshot (RN-01)

`tests/Features/EquivalenceSnapshotTest.php` é o guardião da regra de ouro: com a
config default, a saída do CqFeature deve ser **idêntica** à do FeatureMaker do
`cq/api`. O snapshot de referência fica em `tests/Features/__snapshots__/default_crud`
(domínio `Financial/Finance`, `--features=crud --register-routes`), com o timestamp
da migration normalizado para `0000_00_00_000000`.

**Atualizando os snapshots**: o snapshot só deve ser regenerado quando o
comportamento do FeatureMaker original mudar de forma legítima — nunca para
"fazer o teste passar" mascarando uma regressão (uma divergência é bug nas
classes do pacote, não no snapshot). Para regerar, execute o FeatureMaker do
`cq/api` para o domínio de referência em um diretório temporário, normalize o
nome da migration (timestamp → `0000_00_00_000000`) e copie a árvore gerada para
`tests/Features/__snapshots__/default_crud`, preservando os caminhos relativos
(`app/…`, `tests/…`, `database/…`, `routes/…`).

---

## Licença

Proprietary — uso interno Cq.
