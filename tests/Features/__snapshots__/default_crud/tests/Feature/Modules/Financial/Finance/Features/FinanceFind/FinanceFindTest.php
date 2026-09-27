<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Financial\Finance\Features\FinanceFind;

use Illuminate\Http\Response;
use Tests\Feature\Modules\Financial\Finance\Shared\FinanceTestCase;

final class FinanceFindTest extends FinanceTestCase
{
    /**
     * Localizar registro por id
     *
     * @test
     *
     * @group feature
     */
    public function test_find_finance(): void
    {
        $created = $this->makeFinance(true);

        $endpoint = "{$this->baseUrl}/{$created['id']}";
        $response = $this->json(self::GET, $endpoint);

        $expected = $this->defaultExpected($this->foundExpected());
        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure($expected);
        $this->assertNotEmpty($response->json()['data']);
        $this->assertSame($created['id'], $response->json()['data']['id']);
    }
}
