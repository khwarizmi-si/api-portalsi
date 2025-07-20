@extends('emails.layouts.main')

@section('title', 'Reset Password')

@section('content')
<p>Halo {{ $user->full_name ?? $user->name }},</p>
<p>Kami menerima permintaan untuk mereset password Anda.</p>

<p style="text-align:center;">
    <a href="{{ $url }}" style="padding:12px 24px;background:#1a202c;color:white;text-decoration:none;border-radius:5px;">
        Reset Password
    </a>
</p>

<p>Link ini hanya berlaku selama 60 menit.</p>
<p>Jika Anda tidak meminta reset, abaikan email ini.</p>
@endsection
