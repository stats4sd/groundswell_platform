@component('mail::message')
### New Registration of Interest

Name: {{ $data['name'] }}
Email: {{ $data['email'] }}
Organisation: {{ $data['organisation'] }}
Details: {{ $data['details'] }}


Thanks,<br>
{{ config('app.name') }}
@endcomponent
