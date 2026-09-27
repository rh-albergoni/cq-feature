<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Financial\Finance\Features\FinanceCreate;

use Illuminate\Http\Response;
use Tests\Feature\Modules\Financial\Finance\Shared\FinanceTestCase;

final class FinanceCreateTest extends FinanceTestCase
{
    /**
     * Criar registro
     *
     * @test
     *
     * @group feature
     */
    public function test_create_finance(): void
    {
        $body = $this->makeFinance();

        $endpoint = "{$this->baseUrl}";
        $response = $this->json(self::POST, $endpoint, $body);

        $expected = $this->defaultExpected($this->foundExpected());
        $response
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure($expected);
        $this->assertNotEmpty($response->json()['data']);
        $this->assertDatabaseHas('finances', ['id' => $response->json()['data']['id']]);
    }
}
