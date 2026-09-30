<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Companion app</x-slot>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <img
                src="{{ $qr }}"
                alt="QR code for tenant code {{ $code }}"
                width="168"
                height="168"
                class="rounded-lg bg-white p-2"
            />

            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Tenant code</p>
                <p class="font-mono text-2xl font-semibold tracking-widest">{{ $code }}</p>
                <p class="mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
                    Scan this in the companion app. The code in the QR is the tenant code.
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
