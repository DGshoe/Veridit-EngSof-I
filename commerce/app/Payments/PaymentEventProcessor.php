<?php

namespace App\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\PaymentEvent;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Single path for every provider (ADR-002/006/011). In ONE transaction:
 *   1. inbox: register (provider, event_id) — a repeated delivery stops here;
 *   2. lock the order and apply the status only if it is still pending;
 *   3. approved -> credit the wallet (idempotent per order);
 *   4. outbox: record the event for Notification (REQ07).
 */
class PaymentEventProcessor
{
    public function __construct(private readonly WalletService $wallet) {}

    public function process(ProviderEvent $event): ProcessingResult
    {
        $result = DB::transaction(function () use ($event) {
            $inboxId = (string) Str::uuid();

            $inserted = PaymentEvent::query()->insertOrIgnore([
                'id' => $inboxId,
                'provider' => $event->provider->value,
                'event_id' => $event->eventId,
                'provider_payment_id' => $event->providerPaymentId,
                'status' => $event->status->value,
                'payload' => json_encode($event->payload),
                'created_at' => now(),
            ]);

            if ($inserted === 0) {
                return ProcessingResult::Duplicate;
            }

            $order = $this->findOrder($event);

            $result = $order ? $this->apply($order, $event) : ProcessingResult::Ignored;

            PaymentEvent::query()->whereKey($inboxId)->update([
                'order_id' => $order?->id,
                'result' => $result->value,
                'processed_at' => now(),
            ]);

            return $result;
        });

        Log::info('Evento de pagamento processado.', [
            'provider' => $event->provider->value,
            'event_id' => $event->eventId,
            'status' => $event->status->value,
            'result' => $result->value,
        ]);

        return $result;
    }

    /** Locks the order the event refers to (by our id, when echoed by the provider, or by its payment id). */
    private function findOrder(ProviderEvent $event): ?Order
    {
        // A provider settles only the orders it charged (Mercado Pago: checkout and PIX orders).
        $query = Order::query()->whereIn('payment_method', PaymentMethod::settledBy($event->provider));

        if ($event->orderId !== null) {
            if (! Str::isUuid($event->orderId)) {
                return null;
            }
            $query->whereKey($event->orderId);
        } else {
            $query->where('provider_payment_id', $event->providerPaymentId);
        }

        return $query->lockForUpdate()->first();
    }

    private function apply(Order $order, ProviderEvent $event): ProcessingResult
    {
        // A final order never changes again (paid stays paid).
        if ($order->status->isFinal() || $event->status === PaymentStatus::Pending) {
            return ProcessingResult::Ignored;
        }

        // Paid amount must match the order (defends against a forged/altered charge).
        if ($event->amountCents !== null && $event->amountCents !== $order->amount_cents) {
            Log::warning('Valor pago diferente do pedido: evento ignorado.', [
                'provider' => $event->provider->value,
                'event_id' => $event->eventId,
                'order_id' => $order->id,
                'expected_cents' => $order->amount_cents,
                'paid_cents' => $event->amountCents,
            ]);

            return ProcessingResult::Ignored;
        }

        if ($event->status === PaymentStatus::Approved) {
            $order->update([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
                // Keep the provider's payment id for reconciliation (MP: payment id differs from the preference id)
                'payment_data' => ($order->payment_data ?? []) + ['paid_payment_id' => $event->providerPaymentId],
            ]);

            [$entry] = $this->wallet->credit($order->user_id, $order->credits, 'compra', 'order', $order->id);

            OutboxMessage::record('commerce.order.paid', $this->eventPayload($order) + [
                'balance' => $entry->balance_after,
                'paid_at' => $order->paid_at->toIso8601String(),
            ]);
        } else {
            $order->update(['status' => OrderStatus::Failed, 'failed_at' => now()]);

            OutboxMessage::record('commerce.order.failed', $this->eventPayload($order) + [
                'failed_at' => $order->failed_at->toIso8601String(),
            ]);
        }

        return ProcessingResult::Applied;
    }

    /** Data Notification needs for the e-mail (REQ07). No CPF. */
    private function eventPayload(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'package_code' => $order->package_code,
            'credits' => $order->credits,
            'amount_cents' => $order->amount_cents,
            'payment_method' => $order->payment_method->value,
            'billing_name' => $order->billing_name,
            'billing_email' => $order->billing_email,
        ];
    }
}
