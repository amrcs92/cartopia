Your order is confirmed!

Thank you for shopping at Cartopia. We've successfully received your order, and we're happy to share your purchase details below.

Order #{!! $order->id !!} · {!! $order->shippingMethod->name !!} — {!! $placedAt !!}

@foreach ($order->items as $item)
{!! $item->product_name !!} × {!! $item->quantity !!}  {!! $item->line_total->format() !!}
@endforeach

Subtotal: {!! $order->subtotal->format() !!}
{!! $order->shippingMethod->name !!}: {!! $order->shipping_fee->format() !!}
Total: {!! $order->total->format() !!}

@foreach ($rows as $row)
{!! $row['label'] !!}: {!! $row['value'] !!}
@endforeach

View your order: {!! $url !!}

Cartopia, a small online market for everyday staples.
You're getting this email because you have a Cartopia account.
