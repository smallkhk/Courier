<?php

namespace Tests\Unit;

use App\Enums\ShipmentStatus as S;
use PHPUnit\Framework\TestCase;

class ShipmentStatusTest extends TestCase
{
    public function test_every_transition_targets_a_real_status(): void
    {
        foreach (S::transitions() as $from => $targets) {
            S::from($from);
            foreach ($targets as $to => $roles) {
                S::from($to);
                $this->assertNotEmpty($roles);
            }
        }
        $this->assertCount(count(S::cases()), S::transitions(), 'Every status must be declared in the state machine.');
    }

    public function test_role_rules(): void
    {
        $this->assertTrue(S::OutForDelivery->canTransitionTo(S::Delivered, 'rider'));
        $this->assertFalse(S::OutForDelivery->canTransitionTo(S::Delivered, 'customer'));
        $this->assertFalse(S::PendingPayment->canTransitionTo(S::Booked, 'customer'), 'Customers cannot confirm their own payment.');
        $this->assertFalse(S::PendingPayment->canTransitionTo(S::Booked, 'dispatcher'));
        $this->assertTrue(S::PendingPayment->canTransitionTo(S::Booked, 'system'));
        $this->assertTrue(S::Booked->canTransitionTo(S::Cancelled, 'customer'));
        $this->assertFalse(S::PickedUp->canTransitionTo(S::Cancelled, 'customer'));
        $this->assertFalse(S::InTransit->canTransitionTo(S::Delivered, 'rider'), 'Must be out for delivery first.');
    }

    public function test_terminal_statuses_have_no_exits(): void
    {
        foreach ([S::Delivered, S::Cancelled, S::ReturnedToSender] as $s) {
            $this->assertTrue($s->isTerminal());
            $this->assertSame([], $s->allowedNext('admin'));
        }
    }
}
