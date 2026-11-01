Payment not completed

{!! $intro !!}

@foreach ($rows as $row)
{!! $row['label'] !!}: {!! $row['value'] !!}
@endforeach

@foreach ($ticks as $tick)
- {!! $tick !!}
@endforeach

{!! $button !!}: {!! $url !!}

Cartopia, a small online market for everyday staples.
You're getting this email because you have a Cartopia account.
