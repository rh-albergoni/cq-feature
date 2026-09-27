<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Financial\Finance\Features\FinanceUpdate;

use Illuminate\Http\Response;
use Tests\Feature\Modules\Financial\Finance\Shared\FinanceTestCase;

final class FinanceUpdateTest extends FinanceTestCase
{
    /**
     * Atualizar registro
     *
     * @test
     *
     * @group feature
     */
    public function test_update_finance(): void
    {
        $created = $this->makeFinance(true);
        $body = $this->makeFinance();

        $endpoint = "{$this->baseUrl}/{$created['id']}";
        $response = $this->json(self::PUT, $endpoint, $body);

        $expected = $this->defaultExpected($this->foundExpected());
        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure($expected);
        $this->assertNotEmpty($response->json()['data']);
        $this->assertSame($created['id'], $response->json()['data']['id']);
        $this->assertDatabaseHas('finances', ['id' => $created['id']]);
    }
}
