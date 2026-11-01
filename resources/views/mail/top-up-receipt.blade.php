@extends('mail.layout')

@section('title', $title)

@section('glyph', '+')

@section('intro')
Your top-up went through. It is ready to spend at checkout.
@endsection

@section('url', $url)

@section('button', 'Go to your wallet')

@section('content')
@include('mail.partials.rows', ['rows' => $rows])
@endsection
