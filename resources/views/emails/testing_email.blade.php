@component('mail::message')
### {{ t('Testing Email') }}

{{ t('This is a testing email.') }}

{{ t('Thanks,') }}<br>
{{ config('app.name') }}
@endcomponent