<?php

namespace Database\Factories;

use App\Models\DebitCardTransaction;
use App\Models\Loan;
use App\Models\ScheduledRepayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledRepaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ScheduledRepayment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {

        return [
            'loan_id' => fn () => Loan::factory()->create(),
            'amount' => $this->faker->randomNumber(),
            'currency_code' => $this->faker->randomElement(DebitCardTransaction::CURRENCIES),
            'due_date' => $this->faker->dateTimeBetween('+1 month', '+3 month'),
            'status' => ScheduledRepayment::STATUS_DUE,
        ];
    }

    /**
     * Perform any actions after the model instance has been created.
     *
     * @param ScheduledRepayment $scheduledRepayment
     * @return void
     */

    public function configure(): static
    {
        return $this->afterCreating(function(ScheduledRepayment $scheduledRepayment){
            // Check if the status is 'repaid'
            if ($scheduledRepayment->status === ScheduledRepayment::STATUS_REPAID) {
                // Create entry in ReceivedRepayment
//                ReceivedRepayment::factory()->create([
//                    'loan_id' => $scheduledRepayment->loan_id,
//                    'amount' => $scheduledRepayment->amount,
//                    'currency_code' => $scheduledRepayment->currency_code,
//                ]);

                // Update loan outstanding amount accordingly
                $scheduledRepayment->loan->update([
                    'outstanding_amount' => $scheduledRepayment->loan->outstanding_amount - $scheduledRepayment->amount,
                ]);
            }
        });
    }

}
