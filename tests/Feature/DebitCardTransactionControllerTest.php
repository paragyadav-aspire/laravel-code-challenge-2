<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DebitCard $debitCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        $debitCardTransactions = DebitCardTransaction::factory(3)->create([
            'debit_card_id' => $this->debitCard->id
        ]);

        $response = $this->json('get', '/api/debit-card-transactions', [
            'debit_card_id' => $this->debitCard->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([
                    '*' => [
                        'amount',
                        'currency_code'
                    ],
            ]);
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        $otherDebitCard = DebitCard::factory()->create();
        DebitCardTransaction::factory(3)->create(['debit_card_id' => $otherDebitCard->id]);

        // Get /debit-card-transactions with other user's debit_card_id
        $response = $this->json('get', '/api/debit-card-transactions', [
            'debit_card_id' => $otherDebitCard->id,
        ]);

        // Assert the response status is 403 (Forbidden)
        $response->assertStatus(403);
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        $response = $this->json('post', '/api/debit-card-transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 100,
            'currency_code' => 'SGD',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                    'amount',
                    'currency_code',
            ]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        $otherDebitCard = DebitCard::factory()->create();

        $response = $this->json('post', '/api/debit-card-transactions', [
            'debit_card_id' => $otherDebitCard->id,
            'amount' => 100,
            'currency_code' => 'USD',
        ]);

        $response->assertStatus(403);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        $debitCardTransaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $this->debitCard->id
        ]);

        $response = $this->json('get', "/api/debit-card-transactions/{$debitCardTransaction->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                    'amount',
                    'currency_code'
            ]);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        $otherDebitCard = DebitCard::factory()->create();
        $otherDebitCardTransaction = DebitCardTransaction::factory()->create(['debit_card_id' => $otherDebitCard->id]);

        $response = $this->json('get', "/api/debit-card-transactions/{$otherDebitCardTransaction->id}");
        $response->assertStatus(403);
    }

}
