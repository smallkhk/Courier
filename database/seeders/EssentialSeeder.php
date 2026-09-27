<?php

namespace Database\Seeders;

use App\Models\ContentBlock;
use App\Models\Faq;
use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

/** Safe to run in production and re-run: only inserts what is missing. */
class EssentialSeeder extends Seeder
{
    public function run(): void
    {
        $t = fn (string $event, string $subject, string $email, string $sms) => [$event, $subject, $email, $sms];
        $templates = [
            $t('booking_created', 'Shipment {{tracking_number}} created',
                "Hello {{sender_name}},\n\nYour shipment to {{recipient_name}} in {{destination_city}} has been created.\n\nTracking number: {{tracking_number}}\nTotal: {{total}}\nStatus: {{status}}\n\nTrack it any time: {{tracking_url}}\n\n{{business_name}}",
                '{{business_name}}: Shipment {{tracking_number}} created. Status: {{status}}. Track: {{tracking_url}}'),
            $t('payment_confirmed', 'Payment received for {{tracking_number}}',
                "Hello {{sender_name}},\n\nWe have received and verified your payment ({{payment_reference}}). Shipment {{tracking_number}} is now booked.\n\nTrack: {{tracking_url}}\n\n{{business_name}}", ''),
            $t('payment_failed', 'Payment not completed for {{tracking_number}}',
                "Hello {{sender_name}},\n\nYour payment ({{payment_reference}}) for shipment {{tracking_number}} was not completed. No booking has been confirmed. You can try again from your account or the link in your booking email.\n\n{{business_name}}", ''),
            $t('pickup_scheduled', 'Pickup scheduled — {{tracking_number}}', "Hello {{sender_name}},\n\n{{status_description}}\n\nTrack: {{tracking_url}}\n\n{{business_name}}", '{{business_name}}: Pickup scheduled for {{tracking_number}}.'),
            $t('rider_assigned', 'A rider is assigned — {{tracking_number}}', "Hello {{sender_name}},\n\n{{status_description}}\n\nTrack: {{tracking_url}}\n\n{{business_name}}", ''),
            $t('picked_up', 'Parcel picked up — {{tracking_number}}', "Hello {{sender_name}},\n\n{{status_description}}\n\nTrack: {{tracking_url}}\n\n{{business_name}}", '{{business_name}}: {{tracking_number}} picked up. Track: {{tracking_url}}'),
            $t('in_transit', 'In transit — {{tracking_number}}', "Hello {{sender_name}},\n\n{{status_description}}\n\nTrack: {{tracking_url}}\n\n{{business_name}}", ''),
            $t('out_for_delivery', 'Out for delivery — {{tracking_number}}', "Hello,\n\nShipment {{tracking_number}} for {{recipient_name}} is out for delivery today.\n\nTrack: {{tracking_url}}\n\n{{business_name}}", '{{business_name}}: {{tracking_number}} is out for delivery today. Track: {{tracking_url}}'),
            $t('delivery_failed', 'Delivery attempt — {{tracking_number}}', "Hello,\n\n{{status_description}}\n\nTrack: {{tracking_url}}\nNeed help? Reply to {{support_email}}.\n\n{{business_name}}", '{{business_name}}: Delivery attempt for {{tracking_number}} was not completed. We will contact you. {{tracking_url}}'),
            $t('delivered', 'Delivered — {{tracking_number}}', "Hello,\n\nShipment {{tracking_number}} has been delivered.\n\n{{status_description}}\n\nThank you for shipping with {{business_name}}.", '{{business_name}}: {{tracking_number}} delivered.'),
            $t('return_initiated', 'Return started — {{tracking_number}}', "Hello {{sender_name}},\n\nA return to sender has been started for {{tracking_number}}.\n\nTrack: {{tracking_url}}\n\n{{business_name}}", ''),
            $t('return_completed', 'Returned to sender — {{tracking_number}}', "Hello {{sender_name}},\n\nShipment {{tracking_number}} has been returned to you.\n\n{{business_name}}", ''),
            $t('cancelled', 'Shipment cancelled — {{tracking_number}}', "Hello {{sender_name}},\n\nShipment {{tracking_number}} has been cancelled. {{status_description}}\n\n{{business_name}}", ''),
            $t('support_updated', 'Update on your request {{ticket_reference}}', "Hello {{customer_name}},\n\nThere is an update on your support request \"{{ticket_subject}}\" ({{ticket_reference}}). Status: {{ticket_status}}.\n\nView: {{ticket_url}}\n\n{{business_name}}", ''),
            $t('delivery_code', 'Your delivery code for {{tracking_number}}', "Hello {{recipient_name}},\n\nA parcel from {{sender_name}} is on its way to you. Give this code to the rider only when you receive the parcel: {{delivery_code}}\n\nTrack: {{tracking_url}}\n\n{{business_name}}", '{{business_name}}: Parcel {{tracking_number}} is coming. Delivery code: {{delivery_code}}. Share it only on receipt.'),
            $t('rider_assignment', 'New assignment {{tracking_number}}', "Hello {{rider_name}},\n\nYou have a new assignment: {{tracking_number}} ({{origin_city}} → {{destination_city}}).\n\nOpen the rider portal: {{portal_url}}", '{{business_name}}: New job {{tracking_number}}. Open rider portal: {{portal_url}}'),
        ];
        foreach ($templates as [$event, $subject, $email, $sms]) {
            NotificationTemplate::firstOrCreate(['event' => $event, 'channel' => 'email'], ['subject' => $subject, 'body' => $email]);
            if ($sms !== '') {
                NotificationTemplate::firstOrCreate(['event' => $event, 'channel' => 'sms'], ['body' => $sms]);
            }
        }

        $draft = "## DRAFT — REPLACE BEFORE LAUNCH\n\nThis page is a placeholder. The business owner must replace it with their own text, reviewed by a qualified lawyer, before the site goes live.";
        foreach ([
            'about' => ['About us', "We are a parcel pickup and delivery service.\n\nReplace this text with your company's story, the areas you serve and how customers can reach you."],
            'terms' => ['Terms of service', $draft."\n\n## Topics to cover\n\n- Who may use the service and account responsibilities\n- Booking, pricing, quotes and payment\n- Prohibited and restricted items\n- Liability limits and insurance\n- Cancellations and refunds\n- Governing law"],
            'privacy' => ['Privacy policy', $draft."\n\n## Topics to cover\n\n- What personal data is collected (names, contact details, addresses, delivery evidence, rider location)\n- Why it is collected and the legal basis (e.g. US state privacy laws such as the CCPA/CPRA, the EU/UK GDPR for European customers, PIPEDA in Canada)\n- Cookies and tracking, and how to opt out\n- International data transfers\n- Who it is shared with (payment, SMS and email providers)\n- Retention periods\n- Data subject rights and how to exercise them\n- Contact details of the data protection officer"],
            'delivery-policy' => ['Delivery & claims policy', $draft."\n\n## Topics to cover\n\n- Delivery attempts and what happens after a failed attempt\n- Returns to sender and any fees\n- Proof of delivery\n- How to report damage or loss and the claim deadline\n- Compensation limits and the role of declared value / insurance\n- Cash on delivery terms (if offered)"],
        ] as $key => [$title, $body]) {
            ContentBlock::firstOrCreate(['key' => $key], ['title' => $title, 'body' => $body]);
        }

        if (Faq::count() === 0) {
            foreach ([
                ['How do I track my parcel?', 'Enter your tracking number on the Track page. You will find it in your confirmation email, SMS and receipt.'],
                ['When is my shipment confirmed?', 'For online payments, as soon as the payment provider confirms your payment to us. You will receive an email when it is booked.'],
                ['What if nobody is available to receive the parcel?', 'The rider records the attempt and our team contacts you to arrange another attempt, a hold at a branch or a return, depending on our delivery policy.'],
                ['How is the price calculated?', 'From the pickup and delivery areas, the service you choose, the weight (or volumetric weight for large light parcels), the number of parcels and any optional insurance. You always see the full breakdown before paying.'],
                ['Can I send parcels without an account?', 'If guest booking is enabled you can. With an account you can save addresses and see all your shipments in one place.'],
            ] as $i => [$q, $a]) {
                Faq::create(['question' => $q, 'answer' => $a, 'sort_order' => $i]);
            }
        }
    }
}
