<div>
    @if(isset($data['name']) && $data['name'] === $key)
        <div class="border border-gray-200 rounded-md p-4">
            {{ $data['label'] ?? $data['name'] }} - {{ $data['value'] }}
        </div>
    @elseif(is_string($data))
        STRING {{ $data }}
    @else

        <x-filament::section :heading="$key">
            @foreach($data ?? [] as $key => $value)
                <x-submission-section :key="$key" :data="$value"/>
            @endforeach
        </x-filament::section>
    @endif
</div>
