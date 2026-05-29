@component('mail::message')
### {{ t('New Registration of Interest') }}

{{ t('Name') }}: {{ $data['name'] }}
{{ t('Email') }}: {{ $data['email'] }}
{{ t('Organisation') }}: {{ $data['organisation'] }}
{{ t('Details') }}: {{ $data['details'] }}

{{ t('Thanks,') }}<br>
{{ config('app.name') }}
@endcomponent