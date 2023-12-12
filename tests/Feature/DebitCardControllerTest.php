<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected $debitCards;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        $debitCards = DebitCard::factory(2)->create(['user_id' => $this->user->id, 'disabled_at' => null]);

        $response = $this->json('get', '/api/debit-cards');
        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'number',
                    'type',
                    'expiration_date',
                    'is_active',
                ],
        ])->assertJsonCount(2);

    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        $debitCards = DebitCard::factory(2)->create(['user_id' => $this->user->id, 'disabled_at' => null]);

        $response = $this->json('get', '/api/debit-cards');
        $response->assertStatus(200)
            ->assertJsonCount(2);

        DebitCard::factory(2)->create(['disabled_at' => null]);

        $response = $this->json('get', '/api/debit-cards');
        $response->assertStatus(200)
            ->assertJsonCount(2);
    }

    public function testCustomerCanCreateADebitCard()
    {
        $response = $this->json('post', '/api/debit-cards', [
            'type' => 'visa',
        ]);
        $response->assertStatus(201)
            ->assertJsonStructure([
                    'id',
                    'number',
                    'type',
                    'expiration_date',
                    'is_active'
            ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        $debitCards = DebitCard::factory(2)->create(['user_id' => $this->user->id, 'disabled_at' => null]);
        $debitCard = $debitCards->first();

        $response = $this->json('get', "/api/debit-cards/{$debitCard->id}");
        $response->assertStatus(200)
            ->assertJsonStructure([
                    'id',
                    'number',
                    'type',
                    'expiration_date',
                    'is_active',
            ]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => User::factory()->create()->id]);

        $response = $this->json('get', "/api/debit-cards/{$debitCard->id}");
        $response->assertStatus(403);
    }

    public function testCustomerCanActivateADebitCard()
    {
        $debitCards = DebitCard::factory(2)->create(['user_id' => $this->user->id, 'disabled_at' => null]);
        $debitCard = $debitCards->first();

        $response = $this->json('put', "/api/debit-cards/{$debitCard->id}", [
            'is_active' => true,
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'number',
                'type',
                'expiration_date',
                'is_active',
            ]
            );
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        $debitCards = DebitCard::factory(2)->create(['user_id' => $this->user->id, 'disabled_at' => null]);
        $debitCard = $debitCards->first();

        $response = $this->json('put', "/api/debit-cards/{$debitCard->id}", [
            'is_active' => false,
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'number',
                'type',
                'expiration_date',
                'is_active',
            ])
            ->assertJsonFragment([
                'is_active' => false,
            ]);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        $debitCards = DebitCard::factory(2)->create(['user_id' => $this->user->id, 'disabled_at' => null]);
        $debitCard = $debitCards->first();

        $response = $this->json('put', "/api/debit-cards/{$debitCard->id}", [
            'is_active' => 'invalid_value',
        ]);
        $response->assertStatus(422);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        $debitCards = DebitCard::factory(2)->create(['user_id' => $this->user->id, 'disabled_at' => null]);
        $debitCard = $debitCards->first();

        $response = $this->json('delete', "/api/debit-cards/{$debitCard->id}");
        $response->assertStatus(204);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id, 'disabled_at' => null]);
        $debitCardTransactions = DebitCardTransaction::factory(3)->create([
            'debit_card_id' => $debitCard->id
        ]);
        $response = $this->json('delete', "/api/debit-cards/{$debitCard->id}");
        $response->assertStatus(403);
    }

    // Extra bonus for extra tests :)
}
