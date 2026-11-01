{!! $title !!}

Your top-up went through. It is ready to spend at checkout.

@foreach ($rows as $row)
{!! $row['label'] !!}: {!! $row['value'] !!}
@endforeach

Go to your wallet: {!! $url !!}

Cartopia, a small online market for everyday staples.
You're getting this email because you have a Cartopia account.
