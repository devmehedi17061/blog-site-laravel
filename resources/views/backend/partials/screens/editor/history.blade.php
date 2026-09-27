{{-- Revision history, shared by the post and page editors (lib/revisions.js). --}}
<template x-if="history.open">
  <div class="modal-backdrop" @click.self="closeHistory()" @keydown.escape.window="!$store.dialog.open && closeHistory()">
    <div class="modal history-modal" role="dialog" aria-modal="true" aria-labelledby="history-title">
      <div class="modal-head">
        <span class="h-2.5 w-2.5 bg-accent"></span>
        <span id="history-title">History</span>
        <span class="ml-2 text-[12px] font-normal tracking-normal text-neutral-500 normal-case"
              x-text="`The last ${history.limit} saved versions are kept.`"></span>
        <button type="button" class="btn btn-ghost btn-sm ml-auto" @click="closeHistory()" aria-label="Close the history">✕</button>
      </div>

      <div class="history-body">
        {{-- the versions --}}
        <ol class="history-list" aria-label="Saved versions">
          <li x-show="history.loading" class="p-4 text-[13px] text-neutral-500">Loading…</li>
          <li x-show="!history.loading && !history.rows.length" class="p-4 text-[13px] text-neutral-500">
            No versions yet. One is kept every time this is saved.
          </li>
          <template x-for="row in history.rows" :key="row.id">
            <li>
              <button type="button" class="history-item" :class="history.selected?.id === row.id && 'history-item-active'"
                      @click="selectRevision(row)" :aria-current="history.selected?.id === row.id">
                <span class="flex items-center gap-2">
                  <strong class="text-[13px]" x-text="row.when"></strong>
                  <span class="tag tag-accent !h-[18px] !px-1.5 !text-[10px]" x-show="row.is_current">Current</span>
                </span>
                <span class="history-item-what" x-text="versionTitle(row)"></span>
                <span class="text-[11px] text-neutral-500" x-text="`${row.user ?? 'Unknown'} · ${row.ago}`"></span>
              </button>
            </li>
          </template>
        </ol>

        {{-- one version against the current content --}}
        <section class="history-compare" aria-live="polite">
          <div class="alert mb-3" x-show="history.error" x-text="history.error"></div>

          <template x-if="history.selected">
            <div class="flex min-h-0 flex-col gap-3">
              <div class="flex flex-wrap items-center gap-2">
                <div class="min-w-0">
                  <div class="font-heading text-[15px] font-bold" x-text="`Version from ${history.selected.when}`"></div>
                  <div class="text-[12px] text-neutral-600">
                    Compared with the current content.
                    <del class="diff-del">Red</del> is in this version and gone now;
                    <ins class="diff-add">green</ins> was added since.
                  </div>
                </div>
                <button type="button" class="btn btn-primary ml-auto"
                        x-show="history.canRestore && !history.selected.is_current"
                        @click="restoreRevision()" :disabled="history.restoring || history.comparing">
                  <span x-text="history.restoring ? 'Restoring…' : 'Restore this version'"></span>
                </button>
              </div>

              <p class="alert" x-show="history.compare?.missingSections"
                 x-text="`${history.compare?.missingSections} section(s) in this version used a library section that has been deleted; restoring leaves them out.`"></p>

              <div x-show="history.comparing" class="text-[13px] text-neutral-500">Comparing…</div>

              <template x-if="history.compare && !history.comparing">
                <div class="flex flex-col gap-3">
                  <p class="history-same" x-show="!changedFields.length">
                    This version is the same as the current content.
                  </p>

                  <template x-for="field in changedFields" :key="field.key">
                    <div class="history-field">
                      <div class="history-field-label" x-text="field.label"></div>

                      {{-- a short value: before and after, side by side --}}
                      <template x-if="field.short">
                        <div class="history-pair">
                          <div><span class="history-pair-tag">This version</span><del class="diff-del" x-text="field.value || '(empty)'"></del></div>
                          <div><span class="history-pair-tag">Now</span><ins class="diff-add" x-text="field.now || '(empty)'"></ins></div>
                        </div>
                      </template>

                      {{-- longer text: line by line, changed words marked --}}
                      <template x-if="!field.short">
                        <div class="history-diff">
                          <template x-for="(line, i) in field.lines" :key="i">
                            <div class="history-line" :class="`history-line-${line.kind}`"><template x-for="(part, j) in line.parts" :key="j"><span :class="part.op === '-' ? 'diff-del' : part.op === '+' ? 'diff-add' : ''" x-text="part.text"></span></template><template x-if="!line.parts.some(p => p.text)"><span class="text-neutral-400">&nbsp;</span></template></div>
                          </template>
                        </div>
                      </template>
                    </div>
                  </template>

                  <div x-show="unchangedFields.length">
                    <button type="button" class="btn btn-ghost btn-sm" @click="history.showUnchanged = !history.showUnchanged"
                            x-text="`${history.showUnchanged ? 'Hide' : 'Show'} ${unchangedFields.length} unchanged field${unchangedFields.length === 1 ? '' : 's'}`"></button>
                    <dl class="history-unchanged" x-show="history.showUnchanged">
                      <template x-for="field in unchangedFields" :key="field.key">
                        <div><dt x-text="field.label"></dt><dd x-text="field.value || '(empty)'"></dd></div>
                      </template>
                    </dl>
                  </div>
                </div>
              </template>
            </div>
          </template>
        </section>
      </div>
    </div>
  </div>
</template>
