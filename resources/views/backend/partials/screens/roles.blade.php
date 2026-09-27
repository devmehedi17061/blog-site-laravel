<template x-if="$store.ui.screen === 'roles'">
<div x-data="roles" x-init="init()" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">Access</div>
      <h2>Roles &amp; permissions</h2>
      <div class="page-subtitle">
        A role is a set of permissions. Every user holds exactly one.
      </div>
    </div>

    <button type="button" class="btn btn-primary" x-show="$store.auth.can('roles.create')" @click="startNew()">
      + New role
    </button>
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  <div x-show="loading" class="panel table-empty">Loading roles…</div>

  <div x-show="!loading" class="menu-builder">

    {{-- the roles --}}
    <div class="menu-tree !flex-[1_1_250px]">
      <div class="builder-col-head">
        <span>Roles</span>
        <span class="ml-auto font-normal opacity-70" x-text="`${roles.length} total`"></span>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto">
        <template x-for="role in roles" :key="role.id">
          <div class="menu-row !pl-2" :class="isSelected(role) && 'is-active'">
            <button type="button" class="menu-row-main" @click="select(role)">
              <span class="menu-row-label">
                <span class="truncate" x-text="role.name"></span>
                <span class="role-lock" x-show="role.is_locked">Locked</span>
                <span class="role-lock !bg-accent-100 !text-accent-800" x-show="role.is_own">Yours</span>
              </span>
              <span class="menu-row-meta"
                    x-text="`${role.scope} · ${role.users_count} user${role.users_count === 1 ? '' : 's'} · ${permissionCount(role)}`"></span>
            </button>

            <div class="menu-row-actions">
              <button type="button" title="Duplicate as a new role" x-show="$store.auth.can('roles.create')"
                      @click.stop="startNew(role)">⧉</button>
              <button type="button" title="Delete" x-show="role.can.delete" @click.stop="askDelete(role)">✕</button>
            </div>
          </div>
        </template>

        <div x-show="!roles.length" class="p-5 text-[13px] text-neutral-600">No roles yet.</div>
      </div>

      <p class="menu-locations-note !mt-0 !border-t">
        Sign-in, the dashboard and the header need no permission. Everything else in the sidebar
        is hidden from a role that cannot view it.
      </p>
    </div>

    {{-- the one being edited --}}
    <div class="menu-editor !flex-[3_1_520px]">
      <div class="builder-col-head">
        <span x-text="creating ? 'New role' : (selected?.name ?? 'Role')"></span>
        <span class="ml-auto font-normal opacity-70" x-show="form"
              x-text="`${grantedCount} of ${allSlugs.length} permissions`"></span>
      </div>

      <template x-if="!form">
        <div class="p-5 text-[13px] text-neutral-600">Pick a role to see what it can do.</div>
      </template>

      <template x-if="form">
        <form class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-3" @submit.prevent="save()">

          <template x-if="readOnly && readOnlyReason">
            <div class="role-note" x-text="readOnlyReason"></div>
          </template>

          <template x-if="message">
            <div class="alert" x-text="message"></div>
          </template>

          <div class="grid gap-3 sm:grid-cols-2">
            <div class="field">
              <label for="role-name">Name</label>
              <input id="role-name" class="input" :class="fieldError('name') && 'input-invalid'"
                     x-model="form.name" :disabled="readOnly" placeholder="Contributor">
              <p class="field-error" x-show="fieldError('name')" x-text="fieldError('name')"></p>
            </div>

            <div class="field">
              <label for="role-scope">Scope</label>
              <select id="role-scope" class="input" x-model="form.scope" :disabled="readOnly">
                <template x-for="scope in catalog.scopes" :key="scope.name">
                  <option :value="scope.name" :selected="scope.name === form.scope" x-text="scope.name"></option>
                </template>
              </select>
              <p class="mt-1 text-[11px] text-neutral-500" x-text="scopeHint"></p>
            </div>
          </div>

          <div class="field">
            <label for="role-description">Description <span class="font-normal text-neutral-500">— shown to whoever assigns it</span></label>
            <input id="role-description" class="input" x-model="form.description" :disabled="readOnly"
                   placeholder="What someone in this role is for">
            <p class="field-error" x-show="fieldError('description')" x-text="fieldError('description')"></p>
          </div>

          <div class="field">
            <div class="mb-1 flex items-center gap-2">
              <label class="!mb-0">Permissions</label>
              <span class="text-[11px] text-neutral-500">
                Click a heading to flip its whole row, column or group. Ticking any ability ticks View too.
              </span>
              <button type="button" class="btn btn-ghost btn-sm ml-auto" x-show="!readOnly"
                      @click="setAll(everything, state(everything) !== 'all')"
                      x-text="state(everything) === 'all' ? 'Clear all' : 'Grant all'"></button>
            </div>
            <p class="field-error" x-show="fieldError('permissions')" x-text="fieldError('permissions')"></p>

            <div class="overflow-x-auto border border-line">
              <table class="perm-matrix">
                <thead>
                  <tr>
                    <th class="perm-module"><span class="perm-head !cursor-default">Module</span></th>
                    <template x-for="ability in catalog.abilities" :key="ability">
                      <th>
                        <button type="button" class="perm-head" :disabled="readOnly"
                                @click="flip(columnCells(ability))" :title="`Flip ${ability} for every module`">
                          <span class="perm-state" :data-state="state(columnCells(ability))" x-show="!readOnly"></span>
                          <span x-text="ability"></span>
                        </button>
                      </th>
                    </template>
                  </tr>
                </thead>

                <template x-for="group in catalog.groups" :key="group.label">
                  <tbody>
                    <tr class="perm-group-row">
                      <th :colspan="catalog.abilities.length + 1" class="perm-module">
                        <button type="button" class="perm-head" :disabled="readOnly"
                                @click="flip(groupCells(group))" :title="`Flip everything in ${group.label}`">
                          <span class="perm-state" :data-state="state(groupCells(group))" x-show="!readOnly"></span>
                          <span x-text="group.label"></span>
                        </button>
                      </th>
                    </tr>

                    <template x-for="module in group.modules" :key="module.key">
                      <tr :data-module="module.key">
                        <th class="perm-module">
                          <button type="button" class="perm-head" :disabled="readOnly"
                                  @click="flip(rowCells(module))" :title="`Flip every ability on ${module.label}`">
                            <span class="perm-state" :data-state="state(rowCells(module))" x-show="!readOnly"></span>
                            <span x-text="module.label"></span>
                          </button>
                        </th>
                        <template x-for="ability in catalog.abilities" :key="ability">
                          <td>
                            <template x-if="module.cells[ability]">
                              <label class="perm-cell"
                                     :class="[cellDisabled(module.cells[ability]) && 'is-disabled', !module.cells[ability].grantable && 'is-beyond']"
                                     :title="module.cells[ability].grantable ? module.cells[ability].name : `${module.cells[ability].name} — you do not hold this, so you cannot grant it`">
                                <input type="checkbox" :data-slug="module.cells[ability].slug"
                                       :checked="has(module.cells[ability].slug)"
                                       :disabled="cellDisabled(module.cells[ability])"
                                       @change="toggle(module, ability)">
                              </label>
                            </template>
                          </td>
                        </template>
                      </tr>
                    </template>
                  </tbody>
                </template>
              </table>
            </div>
          </div>

          <div class="mt-auto flex flex-wrap items-center gap-2 border-t border-line pt-3" x-show="!readOnly">
            <span class="counter" x-show="dirty">Unsaved changes</span>
            <button type="button" class="btn btn-ghost btn-sm" x-show="dirty && !creating" @click="discard()">Undo</button>
            <button type="button" class="btn btn-secondary" x-show="creating"
                    @click="form = null; creating = false; roles.length && select(roles[0], true)">Cancel</button>
            <button type="submit" class="btn btn-primary ml-auto" :disabled="saving || (!dirty && !creating)"
                    x-text="saving ? 'Saving…' : (creating ? 'Create role' : 'Save role')"></button>
          </div>
        </form>
      </template>
    </div>
  </div>

  {{-- delete confirmation --}}
  <template x-if="confirming">
    <div class="modal-backdrop" @click.self="cancelDelete()" @keydown.escape.window="cancelDelete()">
      <div class="modal w-[min(480px,100%)]">
        <div class="modal-head"><span class="h-2.5 w-2.5 bg-accent"></span> Delete role</div>
        <div class="modal-body">
          <p class="text-sm leading-relaxed text-neutral-700">
            Delete the <strong class="text-ink" x-text="confirming.name"></strong> role? This cannot be undone.
          </p>

          <template x-if="confirming.users_count > 0">
            <div class="field">
              <label for="role-move">
                <span x-text="confirming.users_count"></span> user(s) hold this role. Move them to
              </label>
              <select id="role-move" class="input" x-model="moveUsersTo">
                <template x-for="role in moveTargets" :key="role.id">
                  <option :value="String(role.id)" :selected="String(role.id) === moveUsersTo" x-text="role.name"></option>
                </template>
                <option value="">No role — they will see only the dashboard</option>
              </select>
            </div>
          </template>
        </div>
        <div class="modal-foot">
          <button class="btn btn-secondary" @click="cancelDelete()">Cancel</button>
          <button class="btn btn-primary" @click="confirmDelete()">Delete role</button>
        </div>
      </div>
    </div>
  </template>
</div>
</template>
