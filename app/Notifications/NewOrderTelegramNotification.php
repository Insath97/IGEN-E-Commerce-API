<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class NewOrderTelegramNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $order;

    /**
     * Create a new notification instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order->load(['user', 'customer', 'items', 'latestPayment']);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    /**
     * Get the telegram representation of the notification.
     */
    public function toTelegram($notifiable)
    {
        $orderNumber = $this->order->order_number;
        $customerName = $this->order->user->name ?? ($this->order->customer->name ?? 'Guest/Unknown');
        $totalAmount = number_format($this->order->total_amount, 2);
        $paymentMethod = str_replace('_', ' ', ucfirst($this->order->latestPayment->payment_method ?? 'N/A'));
        
        // Construct the Admin URL
        $adminOrderUrl = config('app.url') . '/admin/orders/' . $this->order->id;

        // Format items list
        $itemsList = "";
        foreach ($this->order->items as $item) {
            $variantInfo = $item->variant_name ? " ({$item->variant_name})" : "";
            $itemsList .= "• {$item->product_name}{$variantInfo}\n   _{$item->quantity} x LKR " . number_format($item->unit_price, 2) . "_\n";
        }

        return TelegramMessage::create()
            ->content("🚀 *New Order Placed!*\n\n")
            ->line("*Order #:* `{$orderNumber}`")
            ->line("*Customer:* {$customerName}\n")
            ->line("*Items:*")
            ->line($itemsList)
            ->line("*Total Amount:* LKR *{$totalAmount}*")
            ->line("*Payment Method:* {$paymentMethod}")
            ->line("*Status:* " . ucfirst($this->order->order_status))
            ->button('View Order in Admin', $adminOrderUrl);
    }
}
