<div>
    <div class="text-lg font-bold text-green pb-4">
        UPLOAD LOCAL INDICATORS
    </div>


    <div class="text-black pb-6">
        On this page, please add the locally relevant indicators that you identified during the LISP workshop. You may either upload the indicators as an Excel file using the provided
        <a href="{{ url('files/HOLPA_indicator_template.xlsx') }}">template</a>, or enter each indicator manually into the table below.
    </div>

    <div class="py-4 indicator-upload">
        <div class="mx-auto  ">
            <div class="p-6 bg-white  ">
                <!-- local indicator excel template file upload file -->
                {{ $this->form }}
            </div>

            <div class="p-6 bg-white  ">
                <!-- allow user to maintain local indicators (uploaded or manually created) in a table -->
                {{ $this->table }}
            </div>

        </div>
    </div>


</div>