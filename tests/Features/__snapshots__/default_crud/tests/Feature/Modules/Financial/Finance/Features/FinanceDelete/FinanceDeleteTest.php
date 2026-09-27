<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Financial\Finance\Features\FinanceDelete;

use Illuminate\Http\Response;
use Tests\Feature\Modules\Financial\Finance\Shared\FinanceTestCase;

final class FinanceDeleteTest extends FinanceTestCase
{
    /**
     * Deletar registro
     *
     * @test
     *
     * @group feature
     */
    public function test_delete_finance(): void
    {
        $created = $this->makeFinance(true);

        $endpoint = "{$this->baseUrl}/{$created['id']}";
        $response = $this->json(self::DELETE, $endpoint);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing('finances', ['id' => $created['id']]);
    }
}
