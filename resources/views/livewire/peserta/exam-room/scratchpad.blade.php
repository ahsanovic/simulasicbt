<div wire:ignore
     x-data="examScratchpad({{ $attemptId }})"
     x-on:open-scratchpad.window="openScratchpad()"
     class="contents">

    {{-- Tombol melayang --}}
    <div x-show="!open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-90"
         x-transition:enter-end="opacity-100 scale-100"
         class="fixed bottom-6 right-6 z-50 sm:bottom-8 sm:right-8">
        <div class="relative">
            <button type="button"
                    x-on:click="openScratchpad()"
                    x-on:mouseenter="showTip = true"
                    x-on:mouseleave="showTip = false"
                    x-on:focus="showTip = true"
                    x-on:blur="showTip = false"
                    aria-describedby="scratchpad-fab-tooltip"
                    class="flex items-center gap-2 rounded-full bg-amber-500 px-4 py-3 text-white shadow-lg shadow-amber-500/30 transition hover:bg-amber-600 hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-amber-500/40">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
                <span class="text-sm font-semibold leading-none">Coret-coret</span>
            </button>

            <div id="scratchpad-fab-tooltip"
                 role="tooltip"
                 x-show="showTip"
                 x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                 class="pointer-events-none absolute bottom-full right-0 mb-3 w-60 sm:w-64">
                <div class="overflow-hidden rounded-xl border-2 border-amber-200 bg-white shadow-[0_12px_40px_-8px_rgba(15,23,42,0.35)]">
                    <div class="flex items-start gap-2.5 bg-gradient-to-r from-amber-50 to-amber-100/80 px-3.5 py-3">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-500 text-white ring-1 ring-white/30">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                        </span>
                        <div>
                            <p class="text-xs font-bold text-amber-900">Kalkulator Coretan</p>
                            <p class="mt-0.5 text-[11px] font-medium text-amber-700/80">Khusus soal TIU</p>
                        </div>
                    </div>
                    <div class="px-3.5 py-3">
                        <p class="text-xs leading-relaxed text-slate-600">
                            Buka kanvas transparan untuk coret-coret, ketik angka/teks dengan keyboard, atau hapus coretan tertentu tanpa mengganggu soal.
                        </p>
                    </div>
                </div>
                <div class="absolute -bottom-1.5 right-8 h-3 w-3 rotate-45 border-b-2 border-r-2 border-amber-200 bg-white"></div>
            </div>
        </div>
    </div>

    {{-- Overlay kanvas --}}
    <div x-show="open"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[60] flex flex-col">

        {{-- Toolbar --}}
        <div class="flex shrink-0 items-center justify-between border-b border-slate-200/80 bg-white/90 px-4 py-3 backdrop-blur-sm sm:px-6">
            <div class="flex items-center gap-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100">
                    <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-900">Kalkulator Coretan</p>
                    <p class="text-xs text-slate-500">Pena, teks keyboard, atau hapus coretan tertentu</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <div class="flex items-center rounded-lg border border-slate-200 bg-slate-50 p-1">
                    <button type="button"
                            x-on:click="setTool('pen')"
                            :class="activeTool === 'pen' ? 'bg-white text-amber-700 shadow-sm ring-1 ring-amber-200' : 'text-slate-600 hover:text-slate-900'"
                            class="flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-sm font-medium transition"
                            title="Coret dengan mouse">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                        <span class="hidden sm:inline">Pena</span>
                    </button>
                    <button type="button"
                            x-on:click="setTool('text')"
                            :class="activeTool === 'text' ? 'bg-white text-amber-700 shadow-sm ring-1 ring-amber-200' : 'text-slate-600 hover:text-slate-900'"
                            class="flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-sm font-medium transition"
                            title="Ketik angka atau teks di kanvas">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        <span class="hidden sm:inline">Teks</span>
                    </button>
                    <button type="button"
                            x-on:click="setTool('eraser')"
                            :class="activeTool === 'eraser' ? 'bg-white text-amber-700 shadow-sm ring-1 ring-amber-200' : 'text-slate-600 hover:text-slate-900'"
                            class="flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-sm font-medium transition"
                            title="Hapus coretan tertentu">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <span class="hidden sm:inline">Hapus</span>
                    </button>
                </div>
                <button type="button"
                        x-on:click="clearCanvas()"
                        class="ui-btn-secondary px-3 py-2 text-sm">
                    Bersihkan
                </button>
                <button type="button"
                        x-on:click="closeScratchpad()"
                        class="ui-btn-primary bg-amber-500 px-3 py-2 text-sm hover:bg-amber-600">
                    Tutup
                </button>
            </div>
        </div>

        {{-- Area kanvas transparan --}}
        <div class="relative min-h-0 flex-1 bg-slate-900/10">
            <canvas x-ref="canvas"
                    tabindex="0"
                    :class="'absolute inset-0 h-full w-full touch-none outline-none ' + cursorClass()"
                    x-on:mousedown="startDraw($event)"
                    x-on:mousemove="draw($event)"
                    x-on:mouseup="stopDraw()"
                    x-on:mouseleave="stopDraw()"
                    x-on:touchstart="startDraw($event)"
                    x-on:touchmove="draw($event)"
                    x-on:touchend="stopDraw()"
                    x-on:touchcancel="stopDraw()"
                    x-on:keydown="handleKeydown($event)"></canvas>
            <p x-show="activeTool === 'text'"
               x-cloak
               class="pointer-events-none absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-white/90 px-4 py-2 text-xs text-slate-600 shadow-md backdrop-blur-sm">
                Klik posisi → ketik angka/teks → <kbd class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px]">Enter</kbd> untuk simpan
            </p>
        </div>
    </div>
</div>
