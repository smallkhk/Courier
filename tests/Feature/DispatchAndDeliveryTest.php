<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\BookingController;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryProof;
use App\Models\RiderAssignment;
use App\Models\Shipment;
use App\Services\BookingService;
use App\Services\PricingService;
use App\Services\ShipmentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DispatchAndDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Shipment $s;

    private $customer;

    private $dispatcher;

    private $rider;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedNetwork();
        $this->customer = $this->user();
        $this->dispatcher = $this->user('dispatcher');
        $this->rider = $this->user('rider');
        $q = app(PricingService::class)->quote(BookingController::quoteInput($this->normalizedPayload()), $this->customer->id);
        $this->s = app(BookingService::class)->create($this->normalizedPayload(), $q, $this->customer, null, 'online', 'd1')['shipment'];
        app(ShipmentWorkflow::class)->transition($this->s, ShipmentStatus::Booked, null, ['role' => 'system']);
    }

    private function assignment(): RiderAssignment
    {
        return RiderAssignment::where('shipment_id', $this->s->id)->where('rider_id', $this->rider->id)->latest('id')->firstOrFail();
    }

    private function toOutForDelivery(): void
    {
        $this->actingAs($this->dispatcher)->post(route('ops.shipments.assign', $this->s), ['rider_id' => $this->rider->id, 'leg' => 'delivery'])->assertSessionHasNoErrors();
        $a = $this->assignment();
        $this->actingAs($this->rider)->post(route('rider.jobs.status', $a), ['status' => 'picked_up'])->assertSessionHasNoErrors();
        $this->actingAs($this->rider)->post(route('rider.jobs.status', $a), ['status' => 'out_for_delivery'])->assertSessionHasNoErrors();
        $this->assertSame(ShipmentStatus::OutForDelivery, $this->s->fresh()->status);
    }

    private function signature(): string
    {
        $img = imagecreatetruecolor(40, 20);
        ob_start();
        imagepng($img);

        return 'data:image/png;base64,'.base64_encode(ob_get_clean());
    }

    public function test_full_delivery_flow_with_proof_and_customer_timeline(): void
    {
        $this->toOutForDelivery();
        $a = $this->assignment();
        $this->assertSame('accepted', $a->status);

        $photo = UploadedFile::fake()->image('door.jpg', 200, 200);
        $this->actingAs($this->rider)->post(route('rider.jobs.proof', $a), [
            'recipient_name' => 'Bola Recipient', 'signature' => $this->signature(), 'photo' => $photo, 'photo_consent' => '1',
        ])->assertRedirect(route('rider.dashboard'));

        $s = $this->s->fresh();
        $this->assertSame(ShipmentStatus::Delivered, $s->status);
        $this->assertSame('completed', $a->fresh()->status);
        $proof = DeliveryProof::firstOrFail();
        Storage::disk('local')->assertExists($proof->signature_path);
        Storage::disk('local')->assertExists($proof->photo_path);
        $this->assertStringStartsWith('proofs/', $proof->photo_path, 'Proofs are stored on the private disk, not under public/.');

        // Customer sees the complete timeline and can open their proof image.
        $this->actingAs($this->customer)->get(route('account.shipments.show', $s))->assertOk()
            ->assertSee('Out for delivery')->assertSee('Delivered')->assertSee('Received by');
        $this->actingAs($this->customer)->get(route('files.proof', [$proof, 'photo']))->assertOk();
        // Nobody else can.
        $this->actingAs($this->user())->get(route('files.proof', [$proof, 'photo']))->assertNotFound();
        $this->actingAs($this->user('rider'))->get(route('files.proof', [$proof, 'photo']))->assertNotFound();
        $this->assertDatabaseHas('notification_logs', ['shipment_id' => $s->id, 'event' => 'delivered']);
    }

    public function test_cannot_mark_delivered_without_proof(): void
    {
        $this->toOutForDelivery();
        $this->expectException(ValidationException::class);
        app(ShipmentWorkflow::class)->transition($this->s->fresh(), ShipmentStatus::Delivered, $this->rider);
    }

    public function test_status_endpoints_refuse_delivered(): void
    {
        $this->toOutForDelivery();
        $this->actingAs($this->dispatcher)->post(route('ops.shipments.status', $this->s), ['status' => 'delivered'])->assertStatus(422);
        $this->actingAs($this->rider)->postJson(route('api.shipments.events', $this->s), ['status' => 'delivered'])->assertStatus(422);
        $this->assertSame(ShipmentStatus::OutForDelivery, $this->s->fresh()->status);
    }

    public function test_riders_only_access_their_own_assignments(): void
    {
        $this->toOutForDelivery();
        $other = $this->user('rider');
        $a = $this->assignment();
        $this->actingAs($other)->get(route('rider.jobs.show', $a))->assertNotFound();
        $this->actingAs($other)->post(route('rider.jobs.proof', $a), ['recipient_name' => 'X'])->assertNotFound();
        $this->actingAs($other)->postJson(route('api.shipments.proof', $this->s), ['recipient_name' => 'X'])->assertForbidden();
        $this->actingAs($other)->getJson(route('api.shipments.show', $this->s))->assertNotFound();
        $this->assertSame(ShipmentStatus::OutForDelivery, $this->s->fresh()->status);
    }

    public function test_reassignment_ends_previous_assignment_and_is_audited(): void
    {
        $this->actingAs($this->dispatcher)->post(route('ops.shipments.assign', $this->s), ['rider_id' => $this->rider->id, 'leg' => 'pickup']);
        $second = $this->user('rider');
        $this->actingAs($this->dispatcher)->post(route('ops.shipments.assign', $this->s), ['rider_id' => $second->id, 'leg' => 'pickup'])->assertSessionHasNoErrors();
        $this->assertSame('reassigned', $this->assignment()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'dispatch.reassigned']);
        $this->actingAs($this->rider)->get(route('rider.jobs.show', $this->assignment()))->assertOk(); // history view still visible to them
        $this->actingAs($this->rider)->post(route('rider.jobs.status', $this->assignment()), ['status' => 'picked_up'])->assertStatus(422);
    }

    public function test_inactive_rider_cannot_be_assigned(): void
    {
        $this->rider->riderProfile->update(['is_active' => false]);
        $this->actingAs($this->dispatcher)->post(route('ops.shipments.assign', $this->s), ['rider_id' => $this->rider->id, 'leg' => 'delivery'])
            ->assertSessionHasErrors('rider_id');
    }

    public function test_failed_delivery_and_retry_workflow(): void
    {
        $this->toOutForDelivery();
        $a = $this->assignment();
        $this->actingAs($this->rider)->post(route('rider.jobs.failed', $a), ['reason' => 'recipient_unavailable', 'note' => 'No answer at gate'])->assertSessionHasNoErrors();
        $this->assertSame(ShipmentStatus::DeliveryAttempted, $this->s->fresh()->status);
        $attempt = DeliveryAttempt::firstOrFail();
        $this->assertDatabaseHas('notification_logs', ['shipment_id' => $this->s->id, 'event' => 'delivery_failed']);

        $this->actingAs($this->dispatcher)->post(route('ops.attempts.resolve', $attempt), ['resolution' => 'retry', 'retry_on' => now()->addDay()->toDateString(), 'note' => 'INTERNAL-XYZ'])->assertSessionHasNoErrors();
        $this->assertSame(ShipmentStatus::AtDestinationFacility, $this->s->fresh()->status);
        $this->assertSame('retry', $attempt->fresh()->resolution);

        // Every attempt stays in the history; internal notes never reach the customer.
        $this->actingAs($this->customer)->get(route('account.shipments.show', $this->s))->assertSee('Delivery attempted')->assertDontSee('INTERNAL-XYZ');
        $this->assertDatabaseMissing('notification_logs', ['body' => 'INTERNAL-XYZ']);

        // Operations can now reassign for a second attempt.
        $this->actingAs($this->dispatcher)->post(route('ops.shipments.assign', $this->s), ['rider_id' => $this->rider->id, 'leg' => 'delivery'])->assertSessionHasNoErrors();
    }

    public function test_return_workflow(): void
    {
        $this->toOutForDelivery();
        $this->actingAs($this->rider)->post(route('rider.jobs.failed', $this->assignment()), ['reason' => 'recipient_declined']);
        $this->actingAs($this->dispatcher)->post(route('ops.attempts.resolve', DeliveryAttempt::first()), ['resolution' => 'return'])->assertSessionHasNoErrors();
        $this->assertSame(ShipmentStatus::ReturnInitiated, $this->s->fresh()->status);
    }
}
