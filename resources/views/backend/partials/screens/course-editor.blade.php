<template x-if="$store.ui.screen === 'courses' && $store.ui.editing">
<div x-data="courseEditor">

  <div class="editor-bar">
    <button type="button" class="btn btn-ghost btn-sm" @click="backToList()">← Courses</button>

    <span class="font-heading text-[12px] font-bold tracking-[0.08em] text-neutral-600 uppercase"
          x-text="isNew ? 'New course' : 'Edit course'"></span>
    <span class="tag ml-1" :class="draft.status === 'Published' ? 'tag-accent' : 'tag-neutral'" x-text="draft.status"></span>

    <div class="ml-auto flex items-center gap-2">
      <span class="counter" x-show="dirty && !saving">Unsaved changes</span>
      <span class="counter" x-show="saving">Saving…</span>
      <a class="btn btn-secondary btn-sm" x-show="draft.id" :href="draft.public_url" target="_blank" rel="noopener"
         x-text="draft.status === 'Published' ? 'View ↗' : 'Preview ↗'"></a>
      <button type="button" class="btn btn-secondary" @click="save()" :disabled="saving">Save</button>
      <button type="button" class="btn btn-primary" @click="save('Published')" :disabled="saving" x-show="draft.status !== 'Published'">Publish</button>
    </div>
  </div>

  <div class="p-6">
    <template x-if="error">
      <div class="alert mb-5" x-text="error"></div>
    </template>

    <div x-show="loading" class="panel table-empty">Loading the course…</div>

    <div x-show="!loading" class="flex flex-col gap-6 lg:flex-row">

      {{-- main column --}}
      <div class="min-w-0 flex-1">
        <div class="tabs mb-5">
          <button type="button" class="tab" :class="tab === 'details' && 'tab-active'" @click="tab = 'details'">Details</button>
          <button type="button" class="tab" :class="tab === 'curriculum' && 'tab-active'" @click="tab = 'curriculum'">
            Curriculum <span class="ml-1 text-neutral-500" x-text="`(${lessonCount})`"></span>
            <span class="ml-1 inline-block h-2 w-2 bg-accent align-middle" x-show="curriculumDirty" title="Unsaved changes"></span>
          </button>
          <button type="button" class="tab" :class="tab === 'seo' && 'tab-active'" @click="tab = 'seo'">SEO</button>
          <button type="button" class="tab" :class="tab === 'news' && 'tab-active'" @click="openNews()" x-show="!isNew">Announcements</button>
        </div>

        {{-- ── details ─────────────────────────────────────────────── --}}
        <div x-show="tab === 'details'" class="flex flex-col gap-5">
          <div class="panel p-5">
            <input class="title-input" x-model="draft.title" placeholder="Course title">
            <p class="field-error" x-show="fieldError('title')" x-text="fieldError('title')"></p>

            <div class="mt-3 flex items-center gap-2 border-t border-line pt-3">
              <span class="text-[11px] tracking-[0.08em] text-neutral-500 uppercase">/courses/</span>
              <input class="slug-input" x-model="draft.slug" placeholder="built from the title">
            </div>
            <p class="field-error" x-show="fieldError('slug')" x-text="fieldError('slug')"></p>

            <div class="field mt-4">
              <label class="flex">Subtitle <span class="counter ml-auto" x-text="`${(draft.subtitle || '').length} / 300`"></span></label>
              <textarea class="input !min-h-[64px] py-2" maxlength="300" x-model="draft.subtitle"
                        placeholder="One or two lines under the title: what the student will be able to do."></textarea>
              <p class="field-error" x-show="fieldError('subtitle')" x-text="fieldError('subtitle')"></p>
            </div>
          </div>

          <section class="panel">
            <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> About this course</div>
            <div class="p-4">
              {{-- Rebuilt per course, so switching courses gets a fresh editor. --}}
              <template x-if="!loading">
                <div :key="draft.id ?? 'new'">
                  @include('backend.partials.rich-field', [
                    'read' => 'draft.description',
                    'write' => 'draft.description = html',
                    'placeholder' => 'Describe the course: who it is for, what it covers, how it is taught…',
                  ])
                </div>
              </template>
            </div>
          </section>

          <div class="grid gap-5 md:grid-cols-2">
            @foreach (['outcomes' => ['What students will learn', 'e.g. Build a REST API with Laravel'], 'requirements' => ['Requirements', 'e.g. Basic HTML and CSS']] as $list => [$heading, $example])
              <section class="panel">
                <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> {{ $heading }}</div>
                <div class="flex flex-col gap-2 p-4" data-list="{{ $list }}">
                  <template x-for="(line, i) in draft.{{ $list }}" :key="i">
                    <div class="flex items-center gap-2">
                      <input class="input !h-[32px] !text-[13px]" x-model="draft.{{ $list }}[i]" maxlength="200" placeholder="{{ $example }}"
                             @keydown.enter.prevent="addLine('{{ $list }}')">
                      <button type="button" class="row-action row-action-danger" title="Remove" @click="removeLine('{{ $list }}', i)">×</button>
                    </div>
                  </template>
                  <p class="text-[12px] text-neutral-500" x-show="!draft.{{ $list }}.length">Nothing listed yet.</p>
                  <div><button type="button" class="btn btn-secondary btn-sm" @click="addLine('{{ $list }}')">+ Add a line</button></div>
                </div>
              </section>
            @endforeach
          </div>

          <section class="panel">
            <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Intro video</div>
            <div class="field p-4">
              <input class="input" x-model="draft.intro_video" placeholder="https://www.youtube.com/watch?v=…  or a Vimeo link">
              <p class="mt-1.5 text-[11px] text-neutral-500" x-text="draft.intro_video ? videoKind(draft.intro_video) : 'Shown in place of the image on the course page. Optional.'"></p>
              <p class="field-error" x-show="fieldError('intro_video')" x-text="fieldError('intro_video')"></p>
            </div>
          </section>
        </div>

        {{-- ── curriculum ──────────────────────────────────────────── --}}
        <div x-show="tab === 'curriculum'" class="flex flex-col gap-4">
          <div class="flex flex-wrap items-center gap-3 text-[13px] text-neutral-600">
            <span><strong class="text-ink" x-text="curriculum.length"></strong> sections</span>
            <span><strong class="text-ink" x-text="lessonCount"></strong> lessons</span>
            <span><strong class="text-ink" x-text="totalLength"></strong> in total</span>
            <span class="ml-auto">Drag ⋮⋮ to reorder — lessons can move between sections. Changes are kept when you press <strong class="text-ink">Save</strong>.</span>
          </div>

          <template x-for="(section, s) in curriculum" :key="section.id ?? `new-${s}`">
            <section class="panel transition-shadow" :class="isDropTarget('section', s) && 'ring-2 ring-accent'"
                     @dragover.prevent="dragOver('section', s)" @drop.prevent="drop('section', s)">
              <div class="flex items-center gap-2 border-b border-line bg-muted px-3 py-2">
                <span class="drag-handle" draggable="true" @dragstart="dragStart($event, 'section', s)" @dragend="dragEnd()" title="Drag to move the section">⋮⋮</span>
                <button type="button" class="row-action" @click="section._open = section._open === false" :title="section._open === false ? 'Expand' : 'Collapse'">
                  <span x-text="section._open === false ? '▸' : '▾'"></span>
                </button>
                <span class="font-heading text-[11px] font-bold tracking-[0.1em] text-neutral-500 uppercase" x-text="`Section ${s + 1}`"></span>
                <input class="input !h-[32px] flex-1 !font-semibold" x-model="section.title" maxlength="180" placeholder="Section title"
                       :class="curriculumErrors[`sections.${s}.title`] && 'input-invalid'">
                <button type="button" class="row-action" title="Move up" @click="move(curriculum, s, -1)" :disabled="s === 0">↑</button>
                <button type="button" class="row-action" title="Move down" @click="move(curriculum, s, 1)" :disabled="s === curriculum.length - 1">↓</button>
                <button type="button" class="row-action row-action-danger" title="Remove section" @click="removeSection(s)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path></svg>
                </button>
              </div>

              <div x-show="section._open !== false">
                <template x-for="(item, l) in section.lessons" :key="item.id ?? `new-${s}-${l}`">
                  <div class="flex items-center gap-3 border-b border-line px-4 py-2.5 last:border-0"
                       draggable="true" @dragstart.stop="dragStart($event, 'lesson', s, l)" @dragend="dragEnd()"
                       @dragover.prevent.stop="dragOver('lesson', s, l)" @drop.prevent.stop="drop('lesson', s, l)"
                       :class="[Object.keys(curriculumErrors).some(k => k.startsWith(`sections.${s}.lessons.${l}.`)) && 'bg-accent-100',
                                isDropTarget('lesson', s, l) && 'shadow-[inset_0_3px_0_var(--color-accent)]',
                                drag?.kind === 'lesson' && drag.s === s && drag.l === l && 'opacity-40']">
                    <span class="drag-handle" title="Drag to move the lesson">⋮⋮</span>
                    <span class="w-5 text-right text-[11px] text-neutral-500" x-text="l + 1"></span>
                    <span class="tag" :class="{ video: 'tag-accent-2', quiz: 'tag-accent' }[item.type] ?? 'tag-neutral'" x-text="item.type === 'quiz' ? `Quiz · ${item.questions?.length ?? 0}` : (item.type === 'video' ? 'Video' : 'Article')"></span>
                    <button type="button" class="min-w-0 flex-1 truncate text-left text-[14px] font-semibold hover:text-accent" @click="editLesson(s, l)" x-text="item.title"></button>
                    <span x-show="item.type === 'video' && item.video_path" class="tag tag-outline" title="Uploaded — plays only for enrolled students">🔒 Private video</span>
                    <span x-show="item.is_preview" class="tag tag-outline">Free preview</span>
                    <span x-show="item.attachment" class="text-[11px] text-neutral-500" title="Has a download">📎</span>
                    <span class="w-[52px] text-right text-[12px] text-neutral-600" x-text="item.duration_minutes ? `${item.duration_minutes} min` : ''"></span>
                    <div class="row-actions">
                      <button type="button" class="row-action" title="Move up" @click="move(section.lessons, l, -1)" :disabled="l === 0">↑</button>
                      <button type="button" class="row-action" title="Move down" @click="move(section.lessons, l, 1)" :disabled="l === section.lessons.length - 1">↓</button>
                      <button type="button" class="row-action" title="Duplicate" @click="duplicateLesson(s, l)">⧉</button>
                      <button type="button" class="row-action" title="Edit" @click="editLesson(s, l)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
                      </button>
                      <button type="button" class="row-action row-action-danger" title="Remove" @click="removeLesson(s, l)">×</button>
                    </div>
                  </div>
                </template>
                <div class="px-4 py-3">
                  <button type="button" class="btn btn-secondary btn-sm" @click="addLesson(s)">+ Add lesson</button>
                </div>
              </div>
            </section>
          </template>

          <div x-show="!curriculum.length" class="panel table-empty">
            No sections yet. A course is sections (chapters) of lessons — start with the first one.
          </div>

          <div><button type="button" class="btn btn-primary" @click="addSection()">+ Add section</button></div>
        </div>

        {{-- ── announcements ─────────────────────────────────────────── --}}
        <div x-show="tab === 'news'" class="flex flex-col gap-5">
          <form class="panel" @submit.prevent="sendNews()">
            <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> New announcement
              <span class="ml-auto text-[12px] font-normal normal-case tracking-normal text-neutral-400" x-text="`Goes to ${news.students} enrolled student${news.students === 1 ? '' : 's'}`"></span>
            </div>
            <div class="flex flex-col gap-3 p-4">
              <div class="field">
                <label>Title</label>
                <input class="input" x-model="news.draft.title" maxlength="180" required placeholder="New lesson added: deploying with Docker">
                <p class="field-error" x-show="news.errors.title" x-text="news.errors.title?.[0]"></p>
              </div>
              <div class="field">
                <label>Message</label>
                <textarea class="input !min-h-[110px] py-2" x-model="news.draft.body" maxlength="5000" required
                          placeholder="What is new, and what should students do?"></textarea>
                <p class="field-error" x-show="news.errors.body" x-text="news.errors.body?.[0]"></p>
              </div>
              <p class="text-[12px] text-neutral-500">Students get it in their Notifications, and it shows at the top of the course page while they are on it.</p>
              <div><button class="btn btn-primary" :disabled="news.sending" x-text="news.sending ? 'Sending…' : 'Send to students'"></button></div>
            </div>
          </form>

          <section class="panel">
            <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Sent</div>
            <template x-for="row in news.rows" :key="row.id">
              <div class="border-b border-line px-4 py-3 last:border-0">
                <div class="flex items-baseline gap-2">
                  <strong class="text-[14px]" x-text="row.title"></strong>
                  <span class="text-[12px] text-neutral-500" x-text="`${row.author ?? ''} · ${row.date}`"></span>
                  <button type="button" class="row-action row-action-danger ml-auto" title="Delete" @click="deleteNews(row)">×</button>
                </div>
                <p class="mt-1 whitespace-pre-line text-[13px] text-neutral-700" x-text="row.body"></p>
              </div>
            </template>
            <div class="table-empty" x-show="!news.rows.length">Nothing sent yet.</div>
          </section>
        </div>

        {{-- ── seo ──────────────────────────────────────────────────── --}}
        <div x-show="tab === 'seo'" class="panel">
          <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Search engines</div>
          <div class="flex flex-col gap-4 p-5">
            <div class="field">
              <label class="flex">SEO title <span class="counter ml-auto" x-text="`${(draft.meta_title || '').length} / 60`"></span></label>
              <input class="input" x-model="draft.meta_title" maxlength="255" :placeholder="draft.title || 'The course title'">
              <p class="field-error" x-show="fieldError('meta_title')" x-text="fieldError('meta_title')"></p>
            </div>
            <div class="field">
              <label class="flex">Meta description <span class="counter ml-auto" x-text="`${(draft.meta_description || '').length} / 160`"></span></label>
              <textarea class="input !min-h-[80px] py-2" x-model="draft.meta_description" maxlength="320" :placeholder="draft.subtitle || 'Blank: the subtitle is used.'"></textarea>
              <p class="field-error" x-show="fieldError('meta_description')" x-text="fieldError('meta_description')"></p>
            </div>

            {{-- how it reads in Google --}}
            <div class="border border-line bg-surface p-4">
              <div class="text-[12px] text-neutral-600" x-text="`${window.location.host} › courses › ${draft.slug || 'course-slug'}`"></div>
              <div class="text-[18px] leading-snug text-[#1a0dab]" x-text="draft.meta_title || draft.title || 'Course title'"></div>
              <div class="text-[13px] leading-relaxed text-neutral-700" x-text="(draft.meta_description || draft.subtitle || 'No description yet — Google will pick its own text.').slice(0, 160)"></div>
            </div>
          </div>
        </div>
      </div>

      {{-- ── sidebar ─────────────────────────────────────────────────── --}}
      <aside class="flex w-full flex-col gap-5 lg:w-[300px] lg:flex-none">

        <section class="panel">
          <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Publish</div>
          <div class="flex flex-col gap-4 p-4">
            <div class="field">
              <label>Status</label>
              <select class="input" x-model="draft.status">
                <template x-for="status in options.statuses" :key="status">
                  <option :value="status" x-text="status" :selected="draft.status === status"></option>
                </template>
              </select>
              <p class="mt-1.5 text-[11px] text-neutral-500">A draft is hidden from the catalogue; you can still preview it.</p>
            </div>
            <label class="switch-row !p-0">
              <input type="checkbox" class="switch" x-model="draft.is_featured">
              <span><strong>Featured</strong><span>Shown first on the homepage and in the catalogue.</span></span>
            </label>
            <div class="flex gap-2 border-t border-line pt-4">
              <button type="button" class="btn btn-secondary flex-1" @click="save()" :disabled="saving"><span x-text="saving ? 'Saving…' : 'Save'"></span></button>
              <button type="button" class="btn btn-primary flex-1" @click="save('Published')" :disabled="saving" x-show="draft.status !== 'Published'">Publish</button>
            </div>
          </div>
        </section>

        <section class="panel">
          <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Pricing</div>
          <div class="flex flex-col gap-4 p-4">
            <div class="grid grid-cols-2 gap-3">
              <div class="field">
                <label x-text="`Price ${currency}`"></label>
                <input type="number" min="0" step="1" class="input" x-model="draft.price" :class="fieldError('price') && 'input-invalid'">
              </div>
              <div class="field">
                <label x-text="`Sale price ${currency}`"></label>
                <input type="number" min="0" step="1" class="input" x-model="draft.sale_price" placeholder="—" :class="fieldError('sale_price') && 'input-invalid'">
              </div>
            </div>
            <p class="field-error" x-show="fieldError('price')" x-text="fieldError('price')"></p>
            <p class="field-error" x-show="fieldError('sale_price')" x-text="fieldError('sale_price')"></p>
            <p class="text-[11px] leading-relaxed text-neutral-500">
              <strong class="text-neutral-700">0 makes the course free</strong> — students join with one click.
              Paid courses go through the checkout (Settings → LMS).
            </p>
          </div>
        </section>

        <section class="panel">
          <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Course image</div>
          <div class="flex flex-col gap-3 p-4">
            <template x-if="draft.thumbnail">
              <div class="flex flex-col gap-2">
                <img :src="draft.thumbnail.url" alt="" class="aspect-video w-full border border-line-strong object-cover">
                <div class="flex items-center gap-2">
                  <span class="truncate text-[12px] text-neutral-600" x-text="draft.thumbnail.name"></span>
                  <button type="button" class="btn btn-ghost btn-sm ml-auto" @click="chooseThumbnail()">Change</button>
                  <button type="button" class="btn btn-ghost btn-sm text-accent-700" @click="clearThumbnail()">Remove</button>
                </div>
              </div>
            </template>
            <template x-if="!draft.thumbnail">
              <button type="button" class="flex w-full cursor-pointer flex-col items-center gap-2 border border-dashed border-line-strong bg-muted p-6 text-[13px] text-neutral-600 transition hover:border-accent hover:text-accent-800"
                      @click="chooseThumbnail()">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="m21 15-5-5L5 21"></path></svg>
                Choose from the media library
              </button>
            </template>
            <p class="text-[11px] text-neutral-500">16:9 works best (e.g. 1280 × 720).</p>
          </div>
        </section>

        <section class="panel">
          <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Organise</div>
          <div class="flex flex-col gap-4 p-4">
            <div class="field">
              <label>Category</label>
              <select class="input" x-model="draft.category_id">
                <option value="">No category</option>
                <template x-for="category in options.categories" :key="category.id">
                  <option :value="category.id" :selected="String(draft.category_id) === String(category.id)"
                          x-text="' '.repeat((category.depth ?? 0) * 3) + (category.depth ? '– ' : '') + category.name"></option>
                </template>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div class="field">
                <label>Level</label>
                <select class="input" x-model="draft.level">
                  <template x-for="level in options.levels" :key="level">
                    <option :value="level" x-text="level" :selected="draft.level === level"></option>
                  </template>
                </select>
              </div>
              <div class="field">
                <label>Language</label>
                <input class="input" x-model="draft.language" maxlength="40" placeholder="English">
              </div>
            </div>
            <div class="field" x-show="$store.auth.user?.role?.scope !== 'Own content'">
              <label>Instructor</label>
              <select class="input" x-model="draft.user_id">
                <option value="">Me</option>
                <template x-for="person in options.instructors" :key="person.id">
                  <option :value="person.id" x-text="person.name" :selected="String(draft.user_id) === String(person.id)"></option>
                </template>
              </select>
              <p class="field-error" x-show="fieldError('user_id')" x-text="fieldError('user_id')"></p>
            </div>
          </div>
        </section>
      </aside>
    </div>
  </div>

  {{-- ── the lesson modal ──────────────────────────────────────────────── --}}
  <template x-if="lesson">
    <div class="modal-backdrop" @keydown.escape.window="closeLesson()">
      <div class="modal flex max-h-[92vh] w-[min(760px,100%)] flex-col">
        <div class="modal-head">
          <span class="h-2.5 w-2.5 bg-accent"></span>
          <span x-text="lesson._index === null ? 'New lesson' : 'Edit lesson'"></span>
        </div>

        <div class="modal-body flex flex-col gap-4 overflow-y-auto">
          <div class="field">
            <label>Title</label>
            <input class="input" x-model="lesson.title" maxlength="180" placeholder="e.g. Installing Laravel" x-init="$nextTick(() => $el.focus())"
                   :class="lesson._error && 'input-invalid'">
            <p class="field-error" x-show="lesson._error" x-text="lesson._error"></p>
          </div>

          <div class="grid gap-4 sm:grid-cols-3">
            <div class="field">
              <label>Type</label>
              <div class="chip-group">
                <button type="button" class="chip" :class="lesson.type === 'video' && 'chip-active'" @click="lesson.type = 'video'">Video</button>
                <button type="button" class="chip" :class="lesson.type === 'article' && 'chip-active'" @click="lesson.type = 'article'">Article</button>
                <button type="button" class="chip" :class="lesson.type === 'quiz' && 'chip-active'" @click="lesson.type = 'quiz'; if (!lesson.questions.length) addQuestion()">Quiz</button>
              </div>
            </div>
            <div class="field">
              <label>Length (minutes)</label>
              <input type="number" min="0" max="6000" class="input" x-model.number="lesson.duration_minutes">
            </div>
            <div class="field" x-show="curriculum.length > 1">
              <label>Section</label>
              <select class="input" x-model.number="lesson._target" x-init="lesson._target = lesson._section">
                <template x-for="(section, s) in curriculum" :key="s">
                  <option :value="s" x-text="`${s + 1}. ${section.title}`" :selected="s === lesson._section"></option>
                </template>
              </select>
            </div>
          </div>

          {{-- ── the quiz ──────────────────────────────────────────────── --}}
          <div x-show="lesson.type === 'quiz'" class="flex flex-col gap-4 border border-line-strong bg-muted p-4">
            <div class="grid gap-4 sm:grid-cols-3">
              <div class="field">
                <label>Pass mark (%)</label>
                <input type="number" min="1" max="100" class="input" x-model.number="lesson.pass_mark">
              </div>
              <div class="field">
                <label>Tries allowed</label>
                <input type="number" min="0" max="100" class="input" x-model.number="lesson.max_attempts">
                <p class="mt-1 text-[11px] text-neutral-500">0 = unlimited</p>
              </div>
              <label class="switch-row !p-0 self-center">
                <input type="checkbox" class="switch" x-model="lesson.shuffle_questions">
                <span><strong>Shuffle</strong><span>Questions in a new order each try.</span></span>
              </label>
            </div>

            <template x-for="(q, qi) in lesson.questions" :key="qi">
              <div class="border border-line-strong bg-surface p-3" data-question>
                <div class="mb-2 flex flex-wrap items-center gap-2">
                  <span class="grid h-6 w-6 place-items-center bg-neutral-900 font-heading text-[11px] font-bold text-white" x-text="qi + 1"></span>
                  <select class="input !h-[30px] w-[170px] !text-[12px]" :value="q.type" @change="setQuestionType(q, $event.target.value)">
                    <option value="single">One right answer</option>
                    <option value="multiple">Several right answers</option>
                    <option value="true_false">True / false</option>
                  </select>
                  <label class="flex items-center gap-1 text-[12px] text-neutral-600">Points
                    <input type="number" min="1" max="100" class="input !h-[30px] w-[60px] !text-[12px]" x-model.number="q.points">
                  </label>
                  <span class="ml-auto"></span>
                  <button type="button" class="row-action" title="Move up" @click="move(lesson.questions, qi, -1)" :disabled="qi === 0">↑</button>
                  <button type="button" class="row-action" title="Move down" @click="move(lesson.questions, qi, 1)" :disabled="qi === lesson.questions.length - 1">↓</button>
                  <button type="button" class="row-action row-action-danger" title="Remove question" @click="removeQuestion(qi)">×</button>
                </div>
                <textarea class="input !min-h-[56px] py-2 !text-[14px]" x-model="q.question" maxlength="2000" placeholder="The question"></textarea>

                <div class="mt-2 flex flex-col gap-1.5">
                  <template x-for="(opt, oi) in q.options" :key="oi">
                    <div class="flex items-center gap-2">
                      <button type="button" @click="markCorrect(q, oi)"
                              class="grid h-[30px] w-[30px] flex-none place-items-center border text-[13px] font-bold"
                              :class="opt.correct ? 'border-success-800 bg-success-800 text-white' : 'border-line-strong text-neutral-400 hover:border-success-800'"
                              :title="opt.correct ? 'Right answer' : 'Mark as right'">✓</button>
                      <input class="input !h-[30px] !text-[13px]" x-model="opt.text" maxlength="500" :readonly="q.type === 'true_false'"
                             :placeholder="`Answer ${String.fromCharCode(65 + oi)}`">
                      <button type="button" class="row-action" x-show="q.type !== 'true_false' && q.options.length > 2" @click="removeOption(q, oi)" title="Remove answer">×</button>
                    </div>
                  </template>
                  <button type="button" class="self-start text-[12px] font-semibold text-accent-700" x-show="q.type !== 'true_false' && q.options.length < 10" @click="addOption(q)">+ Add answer</button>
                </div>

                <input class="input mt-2 !h-[30px] !text-[12px]" x-model="q.explanation" maxlength="2000" placeholder="Why this is the answer (shown after the quiz) — optional">
              </div>
            </template>

            <div class="flex flex-wrap gap-2">
              <button type="button" class="btn btn-secondary btn-sm" @click="addQuestion('single')">+ One-answer question</button>
              <button type="button" class="btn btn-secondary btn-sm" @click="addQuestion('multiple')">+ Several-answer question</button>
              <button type="button" class="btn btn-secondary btn-sm" @click="addQuestion('true_false')">+ True / false</button>
            </div>
          </div>

          <div class="field" x-show="lesson.type === 'video'">
            <label>Video</label>
            <div class="option-cards !flex-row flex-wrap mb-3">
              <label class="option-card flex-1 min-w-[200px]">
                <input type="radio" value="link" x-model="lesson._source">
                <span>
                  <span class="option-card-label">YouTube / Vimeo link</span>
                  <span class="option-card-summary">Unlisted YouTube works. Anyone with the link can watch it.</span>
                </span>
              </label>
              <label class="option-card flex-1 min-w-[200px]">
                <input type="radio" value="upload" x-model="lesson._source">
                <span>
                  <span class="option-card-label">Upload a private video</span>
                  <span class="option-card-summary">Kept on this site, played only to enrolled students. No public link.</span>
                </span>
              </label>
            </div>

            {{-- a link --}}
            <div x-show="lesson._source === 'link'">
              <input class="input" x-model="lesson.video_url" placeholder="https://www.youtube.com/watch?v=…  ·  https://vimeo.com/…  ·  https://…/lesson.mp4">
              <p class="mt-1.5 text-[11px] text-neutral-500" x-text="lesson.video_url ? videoKind(lesson.video_url) : 'Paste a YouTube or Vimeo link, or the address of an .mp4 file.'"></p>
              <p class="mt-1 text-[11px] text-warning-800">
                YouTube <strong>Private</strong> videos cannot play on other websites — set them to <strong>Unlisted</strong>,
                or choose “Upload a private video” to keep them truly private.
              </p>
            </div>

            {{-- an upload --}}
            <div x-show="lesson._source === 'upload'" class="flex flex-col gap-2">
              <template x-if="lesson._upload">
                <div class="border border-line-strong p-3">
                  <div class="mb-2 flex items-center gap-2 text-[13px]">
                    <span class="min-w-0 flex-1 truncate font-semibold" x-text="lesson._upload.name"></span>
                    <span class="text-neutral-600" x-text="`${lesson._upload.percent}% of ${humanSize(lesson._upload.size)}`"></span>
                    <button type="button" class="btn btn-ghost btn-sm" @click="cancelUpload()">Cancel</button>
                  </div>
                  <div class="h-1.5 bg-neutral-200"><div class="h-full bg-accent transition-[width]" :style="`width:${lesson._upload.percent}%`"></div></div>
                  <p class="mt-2 text-[11px] text-neutral-500">Keep this window open until it finishes.</p>
                </div>
              </template>

              <template x-if="!lesson._upload && lesson.video_path">
                <div class="flex items-center gap-3 border border-line-strong bg-muted px-3 py-2.5 text-[13px]">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="flex-none text-accent"><rect x="2" y="5" width="15" height="14"></rect><path d="m17 10 5-3v10l-5-3"></path></svg>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold" x-text="lesson.video_name"></span>
                    <span class="block text-[11px] text-neutral-500" x-text="`${humanSize(lesson.video_size)} · private`"></span>
                  </span>
                  <button type="button" class="btn btn-ghost btn-sm" @click="$refs.videoInput.click()">Replace</button>
                  <button type="button" class="btn btn-ghost btn-sm text-accent-700" @click="removeUpload()">Remove</button>
                </div>
              </template>

              <template x-if="!lesson._upload && !lesson.video_path">
                <button type="button" class="flex w-full cursor-pointer flex-col items-center gap-1.5 border border-dashed border-line-strong bg-muted p-5 text-[13px] text-neutral-600 transition hover:border-accent hover:text-accent-800"
                        @click="$refs.videoInput.click()">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 16V4M7 9l5-5 5 5"></path><path d="M4 16v4h16v-4"></path></svg>
                  <span class="font-semibold">Choose a video file</span>
                  <span class="text-[11px] text-neutral-500">MP4 is best for every browser · WebM, MOV also work</span>
                </button>
              </template>

              <input type="file" class="hidden" x-ref="videoInput" accept="video/mp4,video/webm,video/quicktime,video/ogg,.mp4,.m4v,.webm,.mov,.ogv" @change="uploadVideo($event)">
              <p class="text-[11px] text-neutral-500" x-show="isNew">Save the course once before uploading videos.</p>
            </div>
          </div>

          <div class="field">
            <label x-text="{ video: 'Notes under the video', quiz: 'Instructions before the quiz (optional)' }[lesson.type] ?? 'Lesson text'"></label>
            @include('backend.partials.rich-field', [
              'read' => 'lesson.body',
              'write' => 'lesson.body = html',
              'placeholder' => 'Explain, summarise, link to resources…',
            ])
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="field">
              <label>Download for students</label>
              <template x-if="lesson.attachment">
                <div class="flex items-center gap-2 border border-line-strong px-3 py-2 text-[13px]">
                  <span class="min-w-0 flex-1 truncate" x-text="lesson.attachment.name"></span>
                  <button type="button" class="btn btn-ghost btn-sm" @click="lesson.media_id = null; lesson.attachment = null">Remove</button>
                </div>
              </template>
              <button type="button" class="btn btn-secondary btn-sm" x-show="!lesson.attachment" @click="chooseAttachment()">Choose a file…</button>
            </div>

            <label class="switch-row !p-0">
              <input type="checkbox" class="switch" x-model="lesson.is_preview">
              <span><strong>Free preview</strong><span>Anyone can open this lesson before enrolling.</span></span>
            </label>
          </div>
        </div>

        <div class="modal-foot">
          <button type="button" class="btn btn-secondary" @click="closeLesson()">Cancel</button>
          <button type="button" class="btn btn-primary" @click="commitLesson()" x-text="lesson._index === null ? 'Add lesson' : 'Done'"></button>
        </div>
      </div>
    </div>
  </template>
</div>
</template>
