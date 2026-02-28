<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Shipped - {{ $order->order_number }}</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .header { background: #d4edda; padding: 10px; text-align: center; border-radius: 4px; color: #155724; }
        .shipping-info { background: #e2e3e5; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .order-details { margin: 20px 0; border-top: 1px solid #eee; padding-top: 10px; }
        .item-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .footer { font-size: 12px; color: #777; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Your Order is on the Way!</h2>
        </div>
        <p>Dear {{ $order->user->name }},</p>
        <p>Great news! Your order <strong>#{{ $order->order_number }}</strong> has been shipped and is now on its way to you.</p>

        <div class="shipping-info">
            <h3>Shipping Information:</h3>
            <p><strong>Courier:</strong> {{ $order->shippingDetail->courier_name }}</p>
            <p><strong>Tracking Number:</strong> <span style="font-family: monospace; font-weight: bold;">{{ $order->shippingDetail->tracking_number }}</span></p>
            @if($order->shippingDetail->estimated_delivery_at)
                <p><strong>Estimated Delivery:</strong> {{ \Carbon\Carbon::parse($order->shippingDetail->estimated_delivery_at)->format('M d, Y') }}</p>
            @endif
        </div>

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

        <p>You can track your order using the tracking number above on the courier's website.</p>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
