<x-filament-panels::page>
    <style>
        .cbx-guide { max-width: 56rem; line-height: 1.65; font-size: .95rem; }
        .cbx-guide h2 { font-size: 1.25rem; font-weight: 700; margin: 2rem 0 .75rem; }
        .cbx-guide h3 { font-size: 1.05rem; font-weight: 600; margin: 1.5rem 0 .5rem; }
        .cbx-guide p, .cbx-guide ul, .cbx-guide ol, .cbx-guide table, .cbx-guide pre, .cbx-guide blockquote { margin: 0 0 1rem; }
        .cbx-guide ul { list-style: disc; padding-left: 1.5rem; }
        .cbx-guide ol { list-style: decimal; padding-left: 1.5rem; }
        .cbx-guide li { margin: .25rem 0; }
        .cbx-guide a { color: var(--primary-600); text-decoration: underline; }
        .cbx-guide code { font-size: .85em; padding: .1rem .3rem; border-radius: .25rem; background: rgb(0 0 0 / .06); }
        .dark .cbx-guide code { background: rgb(255 255 255 / .08); }
        .cbx-guide pre { padding: 1rem; border-radius: .5rem; overflow-x: auto; background: rgb(0 0 0 / .05); }
        .dark .cbx-guide pre { background: rgb(255 255 255 / .06); }
        .cbx-guide pre code { background: none; padding: 0; }
        .cbx-guide table { width: 100%; border-collapse: collapse; font-size: .9rem; display: block; overflow-x: auto; }
        .cbx-guide th, .cbx-guide td { border: 1px solid rgb(128 128 128 / .25); padding: .5rem .75rem; text-align: left; vertical-align: top; }
        .cbx-guide th { font-weight: 600; }
        .cbx-guide blockquote { border-left: 4px solid var(--primary-500); padding: .5rem 1rem; background: rgb(128 128 128 / .07); border-radius: 0 .5rem .5rem 0; }
        .cbx-guide hr { margin: 2rem 0; border-color: rgb(128 128 128 / .25); }
    </style>

    <x-filament::section>
        <div class="cbx-guide">
            {{ $this->getGuideHtml() }}
        </div>
    </x-filament::section>
</x-filament-panels::page>
