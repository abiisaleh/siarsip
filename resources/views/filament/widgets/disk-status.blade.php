<x-filament-widgets::widget class="fi-filament-info-widget">
    <x-filament::section>
        <div class="flex items-center gap-x-3">
            <x-filament::icon icon='heroicon-o-server-stack' class="w-10"/>

            <div class="flex-1">
                <div style="justify-content: space-between" class="flex items-center">
                    <p class="font-semibold">Disk usage</p>

                    <div class="flex flex-col items-end gap-y-1">
                        <p class="text-xs">
                            <span class="text-gray-900">{{$this->usedHuman}} Used</span><span class="text-gray-500 dark:text-gray-400">/{{$this->totalHuman}}</span>
                        </p>
                    </div>
                </div>

                <div style="height: 1rem;" class="flex rounded overflow-hidden bg-gray-50 dark:bg-gray-950">
                    <div style="width: {{$this->percentUsed}}%;" class="bg-primary-600 dark:bg-primary-500 flex flex-col text-center"></div>
                </div>  
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
