@extends('mail.layout')

@section('title', 'Your order is confirmed!')

@section('intro')
Thank you for shopping at Cartopia. We've successfully received your order, and we're happy to share your purchase details below.
@endsection

@section('url', $url)

@section('button', 'View your order')

@section('content')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bg-slip" style="background-color:#FFF4D6;border-radius:14px;font-size:15px;">
<tr><td style="padding:18px 20px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr><td class="muted line2" style="color:#4D5E52;font-size:13px;padding-bottom:10px;border-bottom:2px dashed #C5D0B9;">Order #{{ $order->id }} &middot; {{ $order->shippingMethod->name }}</td>
<td class="muted line2" align="right" style="color:#4D5E52;font-size:13px;padding-bottom:10px;border-bottom:2px dashed #C5D0B9;">{{ $placedAt }}</td></tr>
@foreach ($order->items as $item)
<tr><td class="ink line" style="padding:10px 16px 10px 0;border-bottom:1px solid #D3DCC8;color:#1A2B21;"><strong>{{ $item->product_name }}</strong> <span class="muted" style="color:#4D5E52;">&times; {{ $item->quantity }}</span></td>
<td class="ink line" align="right" style="padding:10px 0;border-bottom:1px solid #D3DCC8;color:#1A2B21;font-weight:700;white-space:nowrap;">{{ $item->line_total->format() }}</td></tr>
@endforeach
<tr><td class="body" style="padding:10px 0 4px;color:#3D4F43;">Subtotal</td><td class="body" align="right" style="padding:10px 0 4px;color:#3D4F43;">{{ $order->subtotal->format() }}</td></tr>
<tr><td class="body" style="padding:4px 0;color:#3D4F43;">{{ $order->shippingMethod->name }}</td><td class="body" align="right" style="padding:4px 0;color:#3D4F43;">{{ $order->shipping_fee->format() }}</td></tr>
<tr><td class="ink line2" style="padding:10px 0 0;border-top:2px dashed #C5D0B9;color:#1A2B21;font-weight:800;font-size:17px;">Total</td>
<td class="ink line2" align="right" style="padding:10px 0 0;border-top:2px dashed #C5D0B9;color:#1A2B21;font-family:Georgia,'Times New Roman',serif;font-size:28px;">{{ $order->total->format() }}</td></tr>
</table>
</td></tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:18px;font-size:15px;">
@foreach ($rows as $row)
<tr><td class="muted" style="padding:4px 20px 4px 0;color:#4D5E52;white-space:nowrap;vertical-align:top;">{{ $row['label'] }}</td>
<td class="ink" align="right" style="padding:4px 0;color:#1A2B21;font-weight:{{ $row['bold'] ? '700' : '400' }};">{{ $row['value'] }}</td></tr>
@endforeach
</table>
@endsection
