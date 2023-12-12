<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
use Carbon\Carbon;

class LoanService
{
    /**
     * Create a Loan
     *
     * @param User $user
     * @param int $amount
     * @param string $currencyCode
     * @param int $terms
     * @param string $processedAt
     *
     * @return Loan
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        // Ensure $processedAt is a valid datetime string
        $processedAt = Carbon::parse($processedAt);

        // Create the loan
        $loan = Loan::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'outstanding_amount' => $amount,
            'currency_code' => $currencyCode,
            'terms' => $terms,
            'processed_at' => $processedAt,
            'status' => Loan::STATUS_DUE,
        ]);

        $this->createRepaymentSchedule($loan);

        return $loan;
    }

    protected function createRepaymentSchedule(Loan $loan): void
    {
        $termLengthInMonths = $loan->terms;
        $monthlyPayment = floor($loan->amount / $termLengthInMonths);
        $dueDate = Carbon::parse($loan->processed_at)->addMonth();
        $monthlyPaymentSum = 0;

        for ($i = 0; $i < $termLengthInMonths - 1; $i++) {
            $monthlyPaymentSum = $monthlyPaymentSum + $monthlyPayment;
            ScheduledRepayment::create([
                'loan_id' => $loan->id,
                'amount' => $monthlyPayment,
                'outstanding_amount' => $monthlyPayment,
                'currency_code' => $loan->currency_code,
                'due_date' => $dueDate->format('Y-m-d'),
                'status' => ScheduledRepayment::STATUS_DUE,
            ]);

            $dueDate->addMonth();
        }

        $pendingAmount = $loan->amount - $monthlyPaymentSum;
        ScheduledRepayment::create([
            'loan_id' => $loan->id,
            'amount' => $pendingAmount,
            'outstanding_amount' => $pendingAmount,
            'currency_code' => $loan->currency_code,
            'due_date' => $dueDate->format('Y-m-d'),
            'status' => ScheduledRepayment::STATUS_DUE,
        ]);
    }

    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param Loan $loan
     * @param int $amount
     * @param string $currencyCode
     * @param string $receivedAt
     *
     * @return ReceivedRepayment
     */

    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        $receivedRepayment = ReceivedRepayment::create([
            'loan_id' => $loan->id,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'received_at' => $receivedAt
        ]);

        $this->updateRepaymentSchedule($loan, $amount);
        $this->updateLoanAmount($loan, $amount);

        return $receivedRepayment;
    }

    /**
     * Update Repayment Schedule for a Loan after repayment
     *
     * @param Loan $loan
     * @param ReceivedRepayment $receivedRepayment
     */
    protected function updateRepaymentSchedule(Loan $loan, $amount): void
    {
        // Assuming a simple scenario where each repayment pays off the next due installment
        $duePayments = $loan->scheduledRepayments()->where('status', ScheduledRepayment::STATUS_DUE);
        $firstDuePayment = $duePayments->first();
        $outstanding_amount = $firstDuePayment-> outstanding_amount;
        $secondDuePayment = $duePayments->skip(1)->take(1)->get();

        if($amount > $outstanding_amount){
            if($secondDuePayment) {
                $firstDuePayment->update([
                    'outstanding_amount' => 0,
                    'status' => ScheduledRepayment::STATUS_REPAID,
                ]);
                $this->updateRepaymentSchedule($loan, ($amount - $outstanding_amount));
            }else{
                // Raise an error
            }

        }elseif ($amount < $outstanding_amount){
            $firstDuePayment->update([
                'outstanding_amount' => $outstanding_amount - $amount,
                'status' => ScheduledRepayment::STATUS_PARTIAL,
            ]);
        }else{
            $firstDuePayment->update([
                'outstanding_amount' => 0,
                'status' => ScheduledRepayment::STATUS_REPAID,
            ]);
        }
    }

    protected function updateLoanAmount(Loan $loan, $amount): void{
        if ($loan->outstanding_amount - $amount == 0) {
            // All repayments are paid, update the loan status to "paid"
            $loan->update([
                'outstanding_amount' => 0,
                'status' => Loan::STATUS_REPAID,
            ]);
        }else{
            $loan->update([
                'outstanding_amount' => $loan->outstanding_amount - $amount]);
        }
    }
}
