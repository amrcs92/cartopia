Your order has been refunded

We've refunded your order in full. Here's where the money went.

Order #{!! $order->id !!} · {!! $order->shippingMethod->name !!} — Refunded {!! $refundedAt !!}

@foreach ($order->items as $item)
{!! $item->product_name !!} × {!! $item->quantity !!}  {!! $item->line_total->format() !!}
@endforeach

Subtotal: {!! $order->subtotal->format() !!}
{!! $order->shippingMethod->name !!}: {!! $order->shipping_fee->format() !!}
Total: {!! $order->total->format() !!}

@foreach ($rows as $row)
{!! $row['label'] !!}: {!! $row['value'] !!}
@endforeach
@if ($hasProviderPart)

Card and PayPal refunds can take 5–10 business days to show.
@endif

View your order: {!! $url !!}

Cartopia, a small online market for everyday staples.
You're getting this email because you have a Cartopia account.
