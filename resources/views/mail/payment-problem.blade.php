@extends('mail.layout')

@section('title', 'Payment not completed')

@section('badgeClass', 'bg-surface outline ink')

@section('badgeStyle', 'background-color:#FFFFFF;color:#1A2B21;')

@section('glyph', '!')

@section('intro', $intro)

@section('url', $url)

@section('button', $button)

@section('content')
@include('mail.partials.rows', ['rows' => $rows])
@include('mail.partials.ticks', ['ticks' => $ticks])
@endsection
