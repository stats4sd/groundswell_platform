@if($getState())
    <div class="flex flex-row">
        <div class="mr-4 text-center basis-1/4 border border-gray-400 rounded-lg p-4 bg-white flex flex-col justify-center space-y-4">
            <div class="mx-auto">{{ QrCode::size(150)->generate($getState()) }}</div>
            <h5 class="">SCAN QR Code in ODK Collect</h5>
        </div>


        <div class="basis-3/4 px-12">
            Your project team has been setup. To link your Android device, install and open
            <b>ODK Collect</b>. When asked for project details, scan the QR code on this page. Your device will be linked and you will have access to the forms listed below.
            <br/><br/>
            Both forms will be available to you. The platform will automatically update the forms when you make local adaptations. If for some reason the survey is not fully up to date with your latest changes, you may manually deploy the latest version by clicking the
            <b>Deploy</b> button in the table below.
        </div>

    </div>
@else
    <div class="p-4">
        <h5 class="text-center text-danger-600">ODK Project not found. Is this platform connected to a value ODK Central server?</h5>
    </div>
@endif
