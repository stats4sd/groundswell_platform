@if (auth()->user() && auth()->user()->latestTeam?->xlsforms()->count() === 0)
<!-- Error message -->
<h3 class="text-white text-center bg-blue">THIS TEAM DOES NOT HAVE ANY ODK FORMS. THIS IS DUE TO AN ISSUE WITH THE TEAM CREATION. PLEASE CONTACT {{ config('mail.to.support') }} FOR SUPPORT BEFORE CONTINUING.</h3>
@endif
