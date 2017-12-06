<?php

namespace Makeable\LaravelEscrow\Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Makeable\LaravelCurrencies\Amount;
use Makeable\LaravelEscrow\Contracts\PaymentGatewayContract;
use Makeable\LaravelEscrow\Escrow;
use Makeable\LaravelEscrow\Exceptions\IllegalEscrowAction;
use Makeable\LaravelEscrow\Exceptions\InsufficientFunds;
use Makeable\LaravelEscrow\SalesAccount;
use Makeable\LaravelEscrow\Tests\DatabaseTestCase;
use Makeable\LaravelEscrow\Tests\Fakes\Product;
use Makeable\LaravelEscrow\Transaction;

class ReleaseEscrowTest extends DatabaseTestCase
{
    /** @test **/
    function it_cant_release_until_committed()
    {
        $this->expectException(IllegalEscrowAction::class);
        $this->escrow->release();

        $this->escrow->commit()->release();
        $this->assertEquals('released', $this->escrow->status->get());
    }

    /** @test **/
    function it_fails_to_release_if_cant_charge_full_amount()
    {
        $this->escrow->commit();

        app(PaymentGatewayContract::class)->shouldFail();

        $this->expectException(\Exception::class);
        $this->escrow->release();
    }

    /** @test **/
    function it_charges_the_remaining_amount()
    {
        $this->escrow->commit()->release();

        $this->assertTrue($this->escrow->deposits->get(0)->getAmount()->equals($this->product->getDepositAmount()));
        $this->assertTrue($this->escrow->deposits->get(1)->getAmount()->equals($this->product->getCustomerAmount()->subtract($this->product->getDepositAmount())));
    }

    /** @test **/
    function it_transfers_funds_to_provider_and_sales_account()
    {
        $this->escrow->commit()->release();

        list ($providerAmount, $feeAmount) = [
            $this->product->getProviderAmount(),
            $this->product->getCustomerAmount()->subtract($this->product->getProviderAmount())
        ];

        $this->assertTrue($this->escrow->getBalance()->equals(Amount::zero()));
        $this->assertTrue($this->provider->getBalance()->equals($providerAmount));
        $this->assertTrue(app(SalesAccount::class)->getBalance()->equals($feeAmount));
    }

//    public function test_it_can_hold_more_funds_than_required()
//    {
//        $escrow = $this->escrow(); // deposit fund 760 DKK
//        $escrow->deposit(factory(Transaction::class)->make(['amount' => 1000, 'currency' => 'DKK']));
//        $escrow->release();
//    }
//

}
