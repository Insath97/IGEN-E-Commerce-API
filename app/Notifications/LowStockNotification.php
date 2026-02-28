<?php

namespace App\Notifications;

use App\Models\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $variant;

    /**
     * Create a new notification instance.
     */
    public function __construct(ProductVariant $variant)
    {
        $this->variant = $variant->load('product');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Low Stock Alert: ' . $this->variant->product->name)
                    ->greeting('Hello Admin,')
                    ->line('The stock for the following product has fallen below the low stock threshold.')
                    ->line('Product: ' . $this->variant->product->name)
                    ->line('Variant: ' . ($this->variant->variant_name ?? 'N/A'))
                    ->line('SKU: ' . ($this->variant->sku ?? 'N/A'))
                    ->line('Current Stock: ' . $this->variant->stock_quantity)
                    ->line('Threshold: ' . $this->variant->low_stock_threshold)
                    ->action('View Product', url('/admin/products/' . $this->variant->product_id))
                    ->line('Please restock this item soon.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'product_id' => $this->variant->product_id,
            'variant_id' => $this->variant->id,
            'product_name' => $this->variant->product->name,
            'variant_name' => $this->variant->variant_name,
            'current_stock' => $this->variant->stock_quantity,
            'threshold' => $this->variant->low_stock_threshold,
            'message' => 'Low stock for product: ' . $this->variant->product->name . ($this->variant->variant_name ? ' (' . $this->variant->variant_name . ')' : ''),
        ];
    }
}
