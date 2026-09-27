<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Financial\Finance\Features\FinanceList;

use Illuminate\Http\Response;
use Tests\Feature\Modules\Financial\Finance\Shared\FinanceTestCase;

final class FinanceListTest extends FinanceTestCase
{
    /**
     * Listar registros
     *
     * @test
     *
     * @group feature
     */
    public function test_list_finances(): void
    {
        $this->makeFinance(true);

        $endpoint = "{$this->baseUrl}?paginate_type=1";
        $response = $this->json(self::GET, $endpoint);

        $expected = $this->defaultListExpected($this->listItemExpected());
        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure($expected);
        $this->assertNotEmpty($response->json()['data']['items']);
    }
}
