<div class="editor-toolbar">

  {{-- block type --}}
  <button type="button" class="tb-btn" :class="active.heading === 0 && 'tb-btn-active'"
          @click="setHeading(0)" title="Paragraph">¶</button>
  <button type="button" class="tb-btn" :class="active.heading === 2 && 'tb-btn-active'"
          @click="setHeading(2)" title="Heading 2">H2</button>
  <button type="button" class="tb-btn" :class="active.heading === 3 && 'tb-btn-active'"
          @click="setHeading(3)" title="Heading 3">H3</button>
  <button type="button" class="tb-btn" :class="active.heading === 4 && 'tb-btn-active'"
          @click="setHeading(4)" title="Heading 4">H4</button>

  <span class="tb-sep"></span>

  {{-- inline marks --}}
  <button type="button" class="tb-btn font-extrabold" :class="active.bold && 'tb-btn-active'"
          @click="run('toggleBold')" title="Bold">B</button>
  <button type="button" class="tb-btn italic" :class="active.italic && 'tb-btn-active'"
          @click="run('toggleItalic')" title="Italic">I</button>
  <button type="button" class="tb-btn underline" :class="active.underline && 'tb-btn-active'"
          @click="run('toggleUnderline')" title="Underline">U</button>
  <button type="button" class="tb-btn line-through" :class="active.strike && 'tb-btn-active'"
          @click="run('toggleStrike')" title="Strikethrough">S</button>
  <button type="button" class="tb-btn font-mono" :class="active.code && 'tb-btn-active'"
          @click="run('toggleCode')" title="Inline code">&lt;&gt;</button>

  <span class="tb-sep"></span>

  {{-- colour --}}
  <div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button type="button" class="tb-btn" @click="open = !open" title="Text colour">
      <span class="flex items-center gap-1">A<span class="h-1 w-3 bg-accent"></span></span>
    </button>
    <div class="swatch-menu" x-show="open" x-cloak>
      <template x-for="c in textColors" :key="c.label">
        <button type="button" class="swatch" :title="c.label"
                :style="c.value ? `background:${c.value}` : 'background:repeating-linear-gradient(45deg,#fff,#fff 4px,#ddd 4px,#ddd 8px)'"
                @click="setColor(c.value); open = false"></button>
      </template>
    </div>
  </div>

  <div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button type="button" class="tb-btn" @click="open = !open" title="Highlight">
      <span class="bg-[#fff3bf] px-1">H</span>
    </button>
    <div class="swatch-menu" x-show="open" x-cloak>
      <template x-for="c in highlights" :key="c.label">
        <button type="button" class="swatch" :title="c.label"
                :style="c.value ? `background:${c.value}` : 'background:repeating-linear-gradient(45deg,#fff,#fff 4px,#ddd 4px,#ddd 8px)'"
                @click="setHighlight(c.value); open = false"></button>
      </template>
    </div>
  </div>

  <span class="tb-sep"></span>

  {{-- blocks --}}
  <button type="button" class="tb-btn" :class="active.bulletList && 'tb-btn-active'"
          @click="run('toggleBulletList')" title="Bullet list">•</button>
  <button type="button" class="tb-btn" :class="active.orderedList && 'tb-btn-active'"
          @click="run('toggleOrderedList')" title="Numbered list">1.</button>
  <button type="button" class="tb-btn" :class="active.blockquote && 'tb-btn-active'"
          @click="run('toggleBlockquote')" title="Quote">&rdquo;</button>
  <button type="button" class="tb-btn" :class="active.codeBlock && 'tb-btn-active'"
          @click="run('toggleCodeBlock')" title="Code block">{ }</button>
  <button type="button" class="tb-btn" @click="run('setHorizontalRule')" title="Divider">—</button>
  <button type="button" class="tb-btn" @click="insertLineBreak()"
          title="Line break — a new line with no gap (Shift+Enter)">↵</button>

  <span class="tb-sep"></span>

  {{-- paragraph spacing for the whole post --}}
  <div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button type="button" class="tb-btn !min-w-0 gap-1 px-2" @click="open = !open"
            :class="draft.content_spacing !== 'normal' && 'tb-btn-active'"
            title="Space between paragraphs">
      <span class="flex items-center gap-1">↕<span class="text-[10px]" x-text="spacingLabel"></span></span>
    </button>

    <div class="menu" x-show="open" x-cloak>
      <template x-for="option in spacings" :key="option.value">
        <button type="button" class="menu-item"
                :class="draft.content_spacing === option.value && 'menu-item-active'"
                @click="setSpacing(option.value); open = false">
          <span x-text="option.label"></span>
          <span class="menu-hint" x-text="option.hint"></span>
        </button>
      </template>
    </div>
  </div>

  <span class="tb-sep"></span>

  {{-- alignment --}}
  <button type="button" class="tb-btn" :class="active.alignLeft && 'tb-btn-active'"
          @click="run('setTextAlign', 'left')" title="Align left">⯇</button>
  <button type="button" class="tb-btn" :class="active.alignCenter && 'tb-btn-active'"
          @click="run('setTextAlign', 'center')" title="Align centre">≡</button>
  <button type="button" class="tb-btn" :class="active.alignRight && 'tb-btn-active'"
          @click="run('setTextAlign', 'right')" title="Align right">⯈</button>

  <span class="tb-sep"></span>

  {{-- link + image --}}
  <button type="button" class="tb-btn" :class="active.link && 'tb-btn-active'"
          @click="addLink()" title="Insert link">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"></path>
      <path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"></path>
    </svg>
  </button>

  <button type="button" class="tb-btn" @click="insertImageFromLibrary()" title="Insert image from the media library">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <rect x="3" y="3" width="18" height="18"></rect>
      <circle cx="8.5" cy="8.5" r="1.5"></circle>
      <path d="m21 15-5-5L5 21"></path>
    </svg>
  </button>

  <button type="button" class="tb-btn" @click="$refs.imageInput.click()" title="Upload a new image" :disabled="uploading">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <path d="M12 19V5"></path><path d="m5 12 7-7 7 7"></path>
    </svg>
  </button>
  <input type="file" accept="image/*" class="hidden" x-ref="imageInput" @change="insertImage($event)">

  <span class="tb-sep"></span>

  <button type="button" class="tb-btn" @click="run('undo')" title="Undo">↶</button>
  <button type="button" class="tb-btn" @click="run('redo')" title="Redo">↷</button>

  <span class="counter ml-auto pr-1" x-show="uploading">Uploading…</span>
  <span class="counter pr-1" x-show="!uploading"
        x-text="`${seo.words} words · ${seo.readingTime} min read`"></span>
</div>
