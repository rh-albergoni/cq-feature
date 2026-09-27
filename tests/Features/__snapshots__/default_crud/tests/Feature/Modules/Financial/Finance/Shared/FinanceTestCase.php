<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Financial\Finance\Shared;

use App\Models\User;
use App\Modules\Financial\Finance\Shared\Models\Finance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class FinanceTestCase extends TestCase
{
    use RefreshDatabase;

    protected string $baseUrl;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->baseUrl = '/api/v1/financial/finances';
    }

    /**
     * Estrutura esperada para registro localizado (Create / Update / Find)
     *
     * @return array
     */
    protected function foundExpected(): array
    {
        return [
            'id',
            'created_by_user_id',
            'updated_by_user_id',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Estrutura esperada para item da listagem
     *
     * @return array
     */
    protected function listItemExpected(): array
    {
        return [
            'id',
            'created_by_user_id',
            'updated_by_user_id',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Monta o conjunto Finance
     *
     * @param  bool  $create  false (padrão) → retorna body para POST/PUT (factory->make);
     *                        true           → persiste no banco de dados (factory->create).
     */
    protected function makeFinance(bool $create = false): array
    {
        $method = $create ? 'create' : 'make';

        return Finance::factory()->{$method}()->toArray();
    }
}
