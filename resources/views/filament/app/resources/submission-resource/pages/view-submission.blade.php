<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>


    <div style='height: 75vh' class="overflow-y-scroll min-w-full divide-y divide-gray-200 border border-gray-300 border-top-0">
            <div class="sticky top-0 bg-gray-50 grid grid-cols-12">
                <div scope="col" style="box-shadow: inset 0 1px 0 black, inset 0 -1px 0 black" class="col-span-2 px-6 py-3 bg-gray-50 shadow-md text-left text-md font-bold uppercase tracking-wider">
                    Type
                </div>
                <div scope="col" style="box-shadow: inset 0 1px 0 black, inset 0 -1px 0 black" class="col-span-2 px-6 py-3 bg-gray-50 shadow-md text-left text-md font-bold uppercase tracking-wider">
                    Name
                </div>
                <div scope="col" style="box-shadow: inset 0 1px 0 black, inset 0 -1px 0 black" class="col-span-4 px-6 py-3 bg-gray-50 shadow-md text-left text-md font-bold uppercase tracking-wider">
                    Label
                </div>
                <div scope="col" style="box-shadow: inset 0 1px 0 black, inset 0 -1px 0 black" class="col-span-4 px-6 py-3 bg-gray-50 shadow-md text-left text-md font-bold uppercase tracking-wider">
                    Value
                </div>
            </div>
            <div class="grid grid-cols-12 bg-white divide-y divide-gray-300">
            @foreach($surveyRowData as $key => $value)
                <x-submission-row :key="$key" :data="$value" :surveyRows="$surveyRows"/>
            @endforeach
            </div>
    </div>


</x-filament-panels::page>
