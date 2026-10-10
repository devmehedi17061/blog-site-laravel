{{--
  A small rich text box (lib/sections/field-editor.js) for any field.

  @param string $read         JS expression giving the current HTML
  @param string $write        JS expression of one argument, `html`, that stores it
  @param string $placeholder  shown while empty

  It reads its value once, when it mounts: put it inside an x-if that is
  rebuilt for each record, so a different record gets a fresh editor.
--}}
<div class="field-editor" x-data="fieldEditor(() => {{ $read }}, (html) => { {{ $write }} }, @js($placeholder ?? 'Write something…'))">
  <div class="field-editor-bar">
    <button type="button" :class="active.bold && 'is-on'" @click="toggle('bold')" title="Bold"><b>B</b></button>
    <button type="button" :class="active.italic && 'is-on'" @click="toggle('italic')" title="Italic"><i>I</i></button>
    <button type="button" :class="active.strike && 'is-on'" @click="toggle('strike')" title="Strikethrough"><s>S</s></button>
    <span class="field-editor-sep"></span>
    <button type="button" :class="active.h3 && 'is-on'" @click="toggle('h3')" title="Heading">H</button>
    <button type="button" :class="active.bullet && 'is-on'" @click="toggle('bullet')" title="Bulleted list">•</button>
    <button type="button" :class="active.ordered && 'is-on'" @click="toggle('ordered')" title="Numbered list">1.</button>
    <span class="field-editor-sep"></span>
    <button type="button" :class="active.link && 'is-on'" @click="setLink()" title="Link">↗</button>
  </div>
  <div x-ref="surface"></div>
</div>
