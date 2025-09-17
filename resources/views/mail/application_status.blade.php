@component('mail::message')
# {{ $processSelectionName }} - UNILAB

Olá, {{ $candidateName }}!

## {{ $status }}

@if(!empty($reasons))
**{{ $message }}**
@foreach($reasons as $reason)
- {{ $reason }}
@endforeach
@endif

@component('mail::button', ['url' => $url])
Ver detalhes da inscrição
@endcomponent

Em caso de dúvidas, consulte o edital e os prazos informados.

@endcomponent