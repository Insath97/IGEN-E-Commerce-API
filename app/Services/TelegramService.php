<?php

namespace App\Services;

use Illuminate\Support\Facades\Notification;
use NotificationChannels\Telegram\TelegramMessage;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\ProductVariant;

class TelegramService
{
    /**
     * Send a simple text message to the default chat.
     * 
     * @param string $message
     * @param string|null $chatId
     * @return void
     */
    public static function sendMessage(string $message, $chatId = null)
    {
        try {
            $chatId = $chatId ?? env('TELEGRAM_CHAT_ID');
            
            if (!$chatId) {
                Log::warning('TelegramService: No Chat ID provided.');
                return;
            }

            Notification::route('telegram', $chatId)
                ->notifyNow(new class($message) extends \Illuminate\Notifications\Notification {
                    protected $msg;
                    public function __construct($msg) { $this->msg = $msg; }
                    public function via($notifiable) { return [\NotificationChannels\Telegram\TelegramChannel::class]; }
                    public function toTelegram($notifiable) {
                        return TelegramMessage::create()->content($this->msg);
                    }
                });

        } catch (\Exception $e) {
            Log::error('TelegramService Error: ' . $e->getMessage());
        }
    }

    /**
     * Send a formatted alert message.
     * 
     * @param string $title
     * @param string $body
     * @param string|null $chatId
     * @return void
     */
    public static function sendAlert(string $title, string $body, $chatId = null)
    {
        $message = "🔔 *{$title}*\n\n{$body}";
        self::sendMessage($message, $chatId);
    }

    /**
     * Send an error report.
     * 
     * @param string $module
     * @param string $error
     * @param string|null $chatId
     * @return void
     */
    public static function sendError(string $module, string $error, $chatId = null)
    {
        $message = "❌ *Error in {$module}*\n\n`{$error}`";
        self::sendMessage($message, $chatId);
    }

    /**
     * Send a formatted New Order alert.
     * 
     * @param Order $order
     * @param string|null $chatId
     * @return void
     */
    public static function sendNewOrderAlert(Order $order, $chatId = null)
    {
        try {
            $chatId = $chatId ?? env('TELEGRAM_CHAT_ID');
            
            if (!$chatId) {
                Log::warning('TelegramService: No Chat ID provided for New Order alert.');
                return;
            }

            // Use the notification class logic which supports buttons
            Notification::route('telegram', $chatId)
                ->notifyNow(new \App\Notifications\NewOrderTelegramNotification($order));

        } catch (\Exception $e) {
            Log::error('TelegramService Order Alert Error: ' . $e->getMessage());
        }
    }

    /**
     * Send a formatted Low Stock alert.
     * 
     * @param ProductVariant $variant
     * @param string|null $chatId
     * @return void
     */
    public static function sendLowStockAlert(ProductVariant $variant, $chatId = null)
    {
        try {
            $chatId = $chatId ?? env('TELEGRAM_CHAT_ID');

            if (!$chatId) return;

            Notification::route('telegram', $chatId)
                ->notifyNow(new \App\Notifications\LowStockNotification($variant));

        } catch (\Exception $e) {
            Log::error('TelegramService Low Stock Alert Error: ' . $e->getMessage());
        }
    }

    /**
     * Get a formatted TelegramMessage for an order.
     * Useful for Notifications.
     */
    public static function getOrderMessage(Order $order): TelegramMessage
    {
        $order->load(['user', 'customer', 'items', 'latestPayment']);
        
        $orderNumber = $order->order_number;
        $customerName = $order->user->name ?? ($order->customer->name ?? 'Guest/Unknown');
        $totalAmount = number_format($order->total_amount, 2);
        $paymentMethod = str_replace('_', ' ', ucfirst($order->latestPayment->payment_method ?? 'N/A'));
        $adminOrderUrl = config('app.url') . '/admin/orders/' . $order->id;

        $itemsList = "";
        foreach ($order->items as $item) {
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
            ->line("*Status:* " . ucfirst($order->order_status))
            ->button('View Order in Admin', $adminOrderUrl);
    }

    /**
     * Get a formatted TelegramMessage for low stock.
     * Useful for Notifications.
     */
    public static function getLowStockMessage(ProductVariant $variant): TelegramMessage
    {
        $variant->loadMissing('product');
        
        $productName = $variant->product->name;
        $variantName = $variant->variant_name ? " ({$variant->variant_name})" : "";
        $currentStock = $variant->stock_quantity;
        $threshold = $variant->low_stock_threshold;
        $sku = $variant->sku ?? 'N/A';
        $adminProductUrl = config('app.url') . '/admin/products/' . $variant->product_id;

        return TelegramMessage::create()
            ->content("⚠️ *Low Stock Alert!*\n\n")
            ->line("*Product:* {$productName}{$variantName}")
            ->line("*SKU:* `{$sku}`")
            ->line("*Current Stock:* {$currentStock}")
            ->line("*Low Stock Threshold:* {$threshold}")
            ->line("\n_Please restock this item soon._")
            ->button('View Product in Admin', $adminProductUrl);
    }
}
