<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Order Received - {{ $order->order_number }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f7;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 700px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #1a2a6c 0%, #b21f1f 50%, #fdbb2d 100%);
            padding: 30px;
            text-align: center;
            color: #ffffff;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .email-body {
            padding: 30px;
            color: #333333;
            line-height: 1.6;
        }
        .section-title {
            color: #1a2a6c;
            font-size: 18px;
            font-weight: 700;
            margin-top: 25px;
            margin-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 5px;
        }
        .order-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
        }
        .info-col {
            flex: 1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            text-align: left;
            background-color: #f8f9fa;
            padding: 12px;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #eeeeee;
            font-size: 15px;
        }
        .item-name {
            font-weight: 600;
            color: #1a2a6c;
        }
        .item-variant {
            font-size: 12px;
            color: #777;
            display: block;
        }
        .totals-table {
            margin-top: 20px;
            float: right;
            width: 250px;
        }
        .totals-table td {
            border-bottom: none;
            padding: 5px 12px;
        }
        .total-row td {
            font-weight: 700;
            font-size: 18px;
            color: #b21f1f;
            padding-top: 10px;
        }
        .address-box {
            background-color: #fff9eb;
            border-left: 4px solid #fdbb2d;
            padding: 15px;
            margin-top: 10px;
            border-radius: 4px;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #888;
            font-size: 13px;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1>New Order Alert</h1>
            <div style="margin-top: 10px; font-size: 16px;">Order #{{ $order->order_number }}</div>
        </div>

        <!-- Body -->
        <div class="email-body">
            <p>Hello Admin,</p>
            <p>A new order has been placed on <strong>{{ config('app.name') }}</strong>. Here are the details:</p>

            <div class="section-title">Customer Information</div>
            <div class="order-info">
                <div class="info-col">
                    <strong>Name:</strong> {{ $order->user->name }}<br>
                    <strong>Email:</strong> {{ $order->user->email }}<br>
                    <strong>Phone:</strong> {{ $order->deliveryAddress->phone ?? 'N/A' }}
                </div>
                <div class="info-col">
                    <strong>Order Date:</strong> {{ $order->created_at->format('M d, Y h:i A') }}<br>
                    <strong>Payment Method:</strong> {{ strtoupper(str_replace('_', ' ', $order->latestPayment->payment_method ?? 'N/A')) }}<br>
                    <strong>Status:</strong> <span style="color: #b21f1f; font-weight: 700;">{{ strtoupper($order->order_status) }}</span>
                </div>
            </div>

            <div class="section-title">Order Items</div>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Unit Price</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td>
                                <span class="item-name">{{ $item->product_name }}</span>
                                @if($item->variant_name)
                                    <span class="item-variant">{{ $item->variant_name }}</span>
                                @endif
                                @if($item->sku)
                                    <span class="item-variant">SKU: {{ $item->sku }}</span>
                                @endif
                            </td>
                            <td style="text-align: center;">{{ $item->quantity }}</td>
                            <td style="text-align: right;">{{ number_format($item->unit_price, 2) }}</td>
                            <td style="text-align: right;">{{ number_format($item->total_price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="clearfix">
                <table class="totals-table">
                    <tr>
                        <td>Subtotal:</td>
                        <td style="text-align: right;">{{ number_format($order->subtotal, 2) }}</td>
                    </tr>
                    @if($order->discount_amount > 0)
                    <tr>
                        <td>Discount:</td>
                        <td style="text-align: right;">-{{ number_format($order->discount_amount, 2) }}</td>
                    </tr>
                    @endif
                    @if($order->tax_amount > 0)
                    <tr>
                        <td>Tax:</td>
                        <td style="text-align: right;">{{ number_format($order->tax_amount, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>Shipping:</td>
                        <td style="text-align: right;">{{ number_format($order->shipping_fee, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td>Total (LKR):</td>
                        <td style="text-align: right;">{{ number_format($order->total_amount, 2) }}</td>
                    </tr>
                </table>
            </div>

            <div class="section-title">Shipping Address</div>
            <div class="address-box">
                <strong>{{ $order->deliveryAddress->name }}</strong><br>
                {{ $order->deliveryAddress->address }}<br>
                {{ $order->deliveryAddress->city }}, {{ $order->deliveryAddress->state }} {{ $order->deliveryAddress->zip_code }}<br>
                {{ $order->deliveryAddress->country ?? 'Sri Lanka' }}
            </div>

            @if($order->notes)
            <div class="section-title">Order Notes</div>
            <div style="font-style: italic; color: #555;">
                {{ $order->notes }}
            </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p>This is an automated notification from <strong>{{ config('app.name') }}</strong>.</p>
            <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
