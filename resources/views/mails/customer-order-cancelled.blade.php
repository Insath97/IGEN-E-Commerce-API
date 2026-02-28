<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Cancelled - {{ $order->order_number }}</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .header { background: #f8d7da; padding: 10px; text-align: center; border-radius: 4px; color: #721c24; }
        .order-details { margin: 20px 0; border-top: 1px solid #eee; padding-top: 10px; }
        .item-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .footer { font-size: 12px; color: #777; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Order Cancelled</h2>
        </div>
        <p>Dear {{ $order->user->name }},</p>
        <p>We are sorry to inform you that your order <strong>#{{ $order->order_number }}</strong> has been cancelled.</p>
        
        @if($order->cancellation_reason)
            <p><strong>Reason for cancellation:</strong> {{ $order->cancellation_reason }}</p>
        @endif

        <div class="order-details">
            <h3>Order Summary:</h3>
            @foreach($order->items as $item)
                <div class="item-row">
                    <span>{{ $item->product_name }} (x{{ $item->quantity }})</span>
                    <span>LKR {{ number_format($item->total_price, 2) }}</span>
                </div>
            @endforeach
            <div class="item-row" style="margin-top: 10px; font-weight: bold;">
                <span>Total Amount</span>
                <span>LKR {{ number_format($order->total_amount, 2) }}</span>
            </div>
        </div>

        <p>If you have any questions or would like to re-order, please visit our website or contact support.</p>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
