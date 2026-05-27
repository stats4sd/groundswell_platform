<x-filament::dropdown>

    <x-slot name="trigger">
        <x-filament::button>
            {{ t("Change Language") }}
        </x-filament::button>
    </x-slot>

    <x-filament::dropdown.list>
        <x-filament::dropdown.list.item
            href="{{ URL::current() . '?locale=en' }}"
            tag="a">
            English
        </x-filament::dropdown.list.item>

        <x-filament::dropdown.list.item
            href="{{ URL::current() . '?locale=es' }}"
            tag="a">
            Español
        </x-filament::dropdown.list.item>

        <x-filament::dropdown.list.item
            href="{{ URL::current() . '?locale=fr' }}"
            tag="a">
            Français
        </x-filament::dropdown.list.item>
    </x-filament::dropdown.list>
</x-filament::dropdown>