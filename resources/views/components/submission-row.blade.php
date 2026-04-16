@if(isset($data['name']) && $data['name'] === $key)

    @switch(collect(explode(' ', $data['type']))->first())

        @case('note')
            <div class="px-6 py-4 col-span-2 text-sm text-gray-900 bg-gray-50">
                note
            </div>
            <div class="px-6 py-4 col-span-2 text-sm text-gray-900 bg-gray-50">
                {{ $data['name'] }}
            </div>
            <div class="px-6 py-4 col-span-8 text-sm text-gray-900 bg-gray-50">
                {{ $data['label'] }}
            </div>
            @break

        @case('calculate')
        @case('start')
        @case('end')
        @case('username')
        @case('deviceid')
        @case('today')
            <div class="px-6 py-4 col-span-2 text-sm text-gray-900 bg-lightgreen">
                {{ $data['type'] }}
            </div>
            <div class="px-6 py-4 col-span-2 text-sm text-gray-900 bg-lightgreen">
                {{ $data['name'] }}
            </div>
            <div class="px-6 py-4 col-span-4 text-sm text-gray-900 bg-lightgreen break-words">
                {{ $data['label'] }}
            </div>
            <div class="px-6 py-4 col-span-4 text-sm text-gray-900 bg-lightgreen">
                {{ $data['value'] }}
            </div>

            @break

        @default
            <div class="px-6 py-4 col-span-2 text-sm text-gray-900">
                {{ $data['type'] }}
            </div>
            <div class="px-6 py-4 col-span-2 text-sm text-gray-900">
                {{ $data['name'] }}
            </div>
            <div class="px-6 py-4 col-span-4 text-sm text-gray-900 break-words">
                {{ $data['label'] }}
            </div>
            <div class="px-6 py-4 col-span-4 text-sm text-gray-900">
                <b>{{ $data['value'] }}</b>
            </div>
    @endswitch
@else

    @if(isset($data['iteration']))
        <div class="col-span-12 px-12 py-4 bg-light-grey text-black font-bold"
        >
            {{ $data['label'] ?? $data['name'] }} - {{ $data['iteration'] }} of {{ $data['count'] }}
        </div>

    @else

        @if($sectionLabel = $surveyRows->where('name', $key)->first()?->defaultLabel?->text)
            <div class="col-span-12 px-12 py-4 bg-blue text-black text-lg font-bold">
                {{ $sectionLabel }}
            </div>
        @endif

        @foreach($data as $key => $value)
            <x-submission-row :key="$key" :data="$value" :surveyRows="$surveyRows"/>
        @endforeach
    @endif
@endif
