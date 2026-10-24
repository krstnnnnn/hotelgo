<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| PayMongo calls POST /webhooks/paymongo by itself when a guest has paid (even if the guest closed the browser).
| No login and no CSRF token here, so the PayMongo SIGNATURE is the only door. Everything is checked before anything is changed.
*/
class WebhookController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['BookingModel', 'PaymentModel', 'NotificationModel']);
        $this->call->library('paymongo');
    }

    private function reply(int $code, array $body): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($body);
        exit;
    }

    public function paymongo()
    {
        $secret = (string) config_item('paymongo_webhook_secret');
        if ($this->paymongo->provider() !== 'paymongo' || $secret === '') $this->reply(503, ['error' => 'webhook is not configured']);
        $raw = (string) file_get_contents('php://input');
        if ($raw === '' || strlen($raw) > 1048576) $this->reply(400, ['error' => 'bad body']);

        $mode = $this->paymongo->verify_signature($raw, $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '', $secret);
        if ($mode === null || $mode !== $this->paymongo->key_mode()) {        // wrong signature, or a test event sent to a live site (and the other way round)
            audit('webhook_rejected', 'bad signature from ' . client_ip());
            $this->reply(400, ['error' => 'invalid signature']);
        }

        [$type, $session] = $this->paymongo->extract_event(json_decode($raw, true) ?: []);
        if ($type !== 'checkout_session.payment.paid' || !is_array($session)) $this->reply(200, ['received' => true, 'ignored' => (string) $type]);

        [$result, $b] = $this->PaymentModel->handle_paid_session($session);
        if ($result === 'retry') $this->reply(503, ['error' => 'could not reach PayMongo, please retry']);     // PayMongo will deliver again later
        if ($b && in_array($result, ['confirmed', 'confirmed_late'], true)) {
            audit('payment_paid_webhook', $b['booking_ref']);
            $this->NotificationModel->booking_confirmed($b);
        } elseif ($b && $result === 'refund_needed') {
            audit('payment_needs_refund', $b['booking_ref'] . ' paid after the room hold ended; refund it in Payments');
            $this->NotificationModel->send((int) $b['id'], $b['user_id'] ? (int) $b['user_id'] : null, 'sms', $b['guest_mobile'], '', 'HOTELGO: we received your payment for ' . $b['booking_ref'] . ' but the room is no longer available. You will be refunded.');
        } elseif ($b && $result === 'duplicate') {
            audit('duplicate_payment', $b['booking_ref'] . ' was paid twice; refund the extra payment in Payments');
        }
        $this->reply(200, ['received' => true, 'result' => $result]);
    }
}
