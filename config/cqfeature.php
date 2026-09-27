<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Configuração do CqFeature
|--------------------------------------------------------------------------
|
| Este arquivo externaliza TODOS os pontos antes hardcoded na lib FeatureMaker
| (namespaces, raízes de filesystem, subpastas de Shared/Features, rotas,
| comando e stubs). Os defaults aqui reproduzem EXATAMENTE o comportamento
| atual do projeto `cq/api`, garantindo a "regra de ouro" (RN-01): com a
| config padrão, a saída do CqFeature é idêntica à do FeatureMaker.
|
| Publicável via: php artisan vendor:publish --tag=cqfeature-config
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Namespace base
    |--------------------------------------------------------------------------
    | Raiz PSR-4 do app consumidor. Vira o placeholder {{RootNamespace}} nos
    | stubs e o prefixo dos namespaces gerados (ex.: App\Modules\...).
    */
    'root_namespace' => 'App',

    /*
    |--------------------------------------------------------------------------
    | Segmentos estruturais (arquitetura DDD Module → Domain → Feature)
    |--------------------------------------------------------------------------
    | Nomes das pastas/namespaces intermediários da árvore de módulos.
    */
    'segments' => [
        'modules' => 'Modules',
        'shared' => 'Shared',
        'features' => 'Features',
        'aggregates' => 'Aggregates',
    ],

    /*
    |--------------------------------------------------------------------------
    | Raízes de filesystem (relativas à base do app consumidor)
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'app' => 'app',
        'tests_base' => 'tests/Feature/Modules',
        'factories_base' => 'database/factories/Modules',
        'migrations_base' => 'database/migrations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Subpastas dos artefatos Shared
    |--------------------------------------------------------------------------
    | Mapa [identificador do artefato => subpasta relativa a Shared/].
    | Reproduz a estrutura usada por PathResolver::sharedFiles().
    */
    'shared_subfolders' => [
        'Entity' => 'Entities',
        'Model' => 'Models',
        'CommandDao' => 'Dao/Commands',
        'QueryDao' => 'Dao/Queries',
        'CommandRepository' => 'Repositories/Commands',
        'QueryRepository' => 'Repositories/Queries',
    ],

    /*
    |--------------------------------------------------------------------------
    | Subpastas dos artefatos de Feature
    |--------------------------------------------------------------------------
    | Mapa [identificador do artefato => subpasta relativa à pasta da feature].
    | Reproduz a estrutura usada por PathResolver para features padrão e custom.
    | O artefato "Readme" fica na raiz da feature (sem subpasta), por isso não
    | é listado aqui.
    */
    'feature_subfolders' => [
        'Controller' => 'Controllers',
        'Service' => 'Services',
        'Request' => 'Requests',
        'Dto' => 'Dtos',
        'ViewDto' => 'Dtos',
        'FilterDto' => 'FilterDtos',
        'CommandRepository' => 'Repositories/Commands',
        'QueryRepository' => 'Repositories/Queries',
        'CommandDao' => 'Dao/Commands',
        'QueryDao' => 'Dao/Queries',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rotas
    |--------------------------------------------------------------------------
    | base       : pasta-base dos arquivos de rota gerados.
    | prefix     : estratégia do prefixo do Route::group (default: kebab do módulo).
    | middleware : middleware aplicado ao Route::group gerado.
    | feature_map: mapeamento feature => {método HTTP, sufixo da URI}
    |              (migrado da constante FEATURE_ROUTE_MAP do RouteRegistry).
    */
    'routes' => [
        'base' => 'routes/api/modules',
        'prefix' => 'module_kebab',
        'middleware' => ['api', 'JWT', 'cors', 'localization'],
        'feature_map' => [
            'create' => ['method' => 'post', 'suffix' => ''],
            'list' => ['method' => 'get', 'suffix' => ''],
            'find' => ['method' => 'get', 'suffix' => '/{id}'],
            'update' => ['method' => 'put', 'suffix' => '/{id}'],
            'delete' => ['method' => 'delete', 'suffix' => '/{id}'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Comando Artisan
    |--------------------------------------------------------------------------
    | Nome do comando registrado (default: `php artisan feature`).
    */
    'command' => [
        'name' => 'feature',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stubs
    |--------------------------------------------------------------------------
    | package_path  : diretório dos stubs internos ao pacote.
    | published_path: diretório de stubs publicados no projeto (precedência
    |                 sobre os do pacote). null = usar apenas os do pacote.
    |                 Ex.: base_path('stubs/cqfeature').
    */
    'stubs' => [
        'package_path' => __DIR__.'/../stubs',
        'published_path' => null,
    ],
];
