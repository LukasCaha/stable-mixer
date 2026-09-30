<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Speech-to-text</x-slot>
        <x-slot name="description">This is the web process. A queue worker only matches it after that worker restarts.</x-slot>

        <div class="space-y-1 text-sm">
            @foreach ($lines as $line)
                <p>{{ $line }}</p>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
