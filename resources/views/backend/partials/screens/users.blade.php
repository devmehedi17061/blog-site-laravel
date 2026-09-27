<template x-if="$store.ui.screen === 'users'">
<div x-data="users" x-init="init()" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">Access</div>
      <h2>User management</h2>
      <div class="page-subtitle">Who can sign in to the panel, and which role decides what they see</div>
    </div>

    <button x-show="$store.auth.can('users.create')" @click="openCreate()" class="btn btn-primary">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
        <path d="M5 12h14"></path><path d="M12 5v14"></path>
      </svg>
      New user
    </button>
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  <div class="panel">
    <div class="toolbar">
      <div class="chip-group">
        <template x-for="status in statusFilters" :key="status">
          <button class="chip" :class="query.status === status && 'chip-active'"
                  @click="setStatus(status)" x-text="status"></button>
        </template>
      </div>

      <span class="toolbar-label">Role</span>
      <select class="input !h-[30px] w-[150px] !text-xs" @change="setRole($event.target.value)" aria-label="Filter by role">
        <option value="All">All roles</option>
        <template x-for="role in options.roles" :key="role.id">
          <option :value="role.id" x-text="`${role.name} (${role.users_count})`"></option>
        </template>
      </select>

      <div class="relative ml-auto flex-[0_1_240px] min-w-[160px]">
        <input class="input !h-[30px] !text-xs" placeholder="Filter by name or email…"
               x-model="query.search" @input="searchDebounced()">
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="data-table min-w-[860px]">
        <thead>
          <tr>
            <th class="cell-index">#</th>
            <template x-for="column in columns" :key="column.key">
              <th :class="column.sortable && 'sortable'"
                  @click="column.sortable && sortBy(column.sortKey || column.key)">
                <span x-text="column.label"></span>
                <span class="ml-1 text-[9px] text-accent-300"
                      x-text="column.sortable ? sortIcon(column.sortKey || column.key) : ''"></span>
              </th>
            </template>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="(row, i) in rows" :key="row.id">
            <tr>
              <td class="cell-index" x-text="meta.from + i"></td>
              <td>
                <div class="flex items-center gap-2.5">
                  <div class="grid h-[30px] w-[30px] flex-none place-items-center font-heading text-[11px] font-bold text-white"
                       :class="row.status === 'Active' ? 'bg-neutral-900' : 'bg-neutral-500'"
                       x-text="row.initials"></div>
                  <div class="flex min-w-0 flex-col leading-tight">
                    <span class="cell-strong truncate">
                      <span x-text="row.name"></span>
                      <span class="ml-1 text-[10px] font-semibold text-accent-700" x-show="row.is_self">you</span>
                    </span>
                    <span class="truncate text-[12px] text-neutral-600" x-text="row.email"></span>
                  </div>
                </div>
              </td>
              <td>
                <span class="tag" :class="roleTag(row.role)" x-text="row.role?.name ?? 'No role'"></span>
              </td>
              <td><span class="tag" :class="statusTag(row.status)" x-text="row.status"></span></td>
              <td class="cell-muted cell-nowrap" x-text="contentSummary(row)"></td>
              <td class="cell-muted cell-nowrap" x-text="row.last_seen_at ?? 'Never'"></td>
              <td class="cell-muted cell-nowrap" x-text="row.joined"></td>
              <td>
                <div class="row-actions">
                  <button x-show="row.can.update" @click="openEdit(row)" title="Edit" class="row-action">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                    </svg>
                  </button>
                  <button x-show="row.can.delete" @click="askDelete(row)" title="Delete" class="row-action row-action-danger">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path>
                    </svg>
                  </button>
                  <span x-show="!row.can.update && !row.can.delete" class="text-[11px] text-neutral-500"
                        title="This account has access you do not hold">—</span>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div x-show="loading" class="table-empty">Loading users…</div>
    <div x-show="isEmpty" class="table-empty">No users match these filters.</div>

    <div class="pagination-bar">
      <span class="pagination-count" x-text="resultCount"></span>
      <button class="page-btn" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1">Prev</button>
      <template x-for="page in pageNumbers" :key="page">
        <button class="page-btn" :class="page === meta.current_page && 'page-btn-active'"
                @click="goToPage(page)" x-text="page"></button>
      </template>
      <button class="page-btn" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page">Next</button>
    </div>
  </div>

  {{-- create / edit --}}
  <template x-if="modal">
    <div class="modal-backdrop" @click.self="closeModal()" @keydown.escape.window="closeModal()">
      <form class="modal w-[min(520px,100%)]" @submit.prevent="save()" autocomplete="off">
        <div class="modal-head">
          <span class="h-2.5 w-2.5 bg-accent"></span>
          <span x-text="modal.mode === 'new' ? 'New user' : 'Edit user'"></span>
        </div>

        <div class="modal-body">
          <template x-if="modal.message">
            <div class="alert" x-text="modal.message"></div>
          </template>

          <div class="grid gap-3 sm:grid-cols-2">
            <div class="field">
              <label for="user-name">Name</label>
              <input id="user-name" class="input" :class="fieldError('name') && 'input-invalid'"
                     x-model="modal.draft.name" placeholder="Full name" autofocus>
              <p class="field-error" x-show="fieldError('name')" x-text="fieldError('name')"></p>
            </div>

            <div class="field">
              <label for="user-email">Email</label>
              <input id="user-email" type="email" class="input" :class="fieldError('email') && 'input-invalid'"
                     x-model="modal.draft.email" placeholder="name@example.com" autocomplete="off">
              <p class="field-error" x-show="fieldError('email')" x-text="fieldError('email')"></p>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div class="field">
              <label for="user-role">Role</label>
              <select id="user-role" class="input" :class="fieldError('role_id') && 'input-invalid'"
                      x-model.number="modal.draft.role_id" :disabled="modal.draft.is_self">
                <option value="" disabled>Pick a role…</option>
                <template x-for="role in options.roles" :key="role.id">
                  <option :value="role.id" :disabled="roleDisabled(role)"
                          :selected="role.id === modal.draft.role_id"
                          x-text="role.assignable ? role.name : `${role.name} — beyond your access`"></option>
                </template>
              </select>
              <p class="field-error" x-show="fieldError('role_id')" x-text="fieldError('role_id')"></p>
            </div>

            <div class="field">
              <label for="user-status">Status</label>
              <select id="user-status" class="input" :class="fieldError('status') && 'input-invalid'"
                      x-model="modal.draft.status" :disabled="modal.draft.is_self">
                <template x-for="status in options.statuses" :key="status">
                  <option :value="status" :selected="status === modal.draft.status" x-text="status"></option>
                </template>
              </select>
              <p class="field-error" x-show="fieldError('status')" x-text="fieldError('status')"></p>
            </div>
          </div>

          <p class="-mt-1 text-[12px] text-neutral-600" x-show="selectedRole">
            <span class="font-semibold text-ink" x-text="selectedRole?.scope"></span> —
            <span x-text="selectedRole?.users_count"></span> user(s) hold this role.
            <span x-show="modal.draft.status === 'Suspended'">A suspended account is signed out at once and cannot sign back in.</span>
          </p>
          <p class="-mt-1 text-[12px] text-neutral-600" x-show="modal.draft.is_self">
            This is your own account: another admin has to change its role or suspend it.
          </p>

          <div class="field">
            <label for="user-password">
              Password
              <span class="font-normal text-neutral-500"
                    x-text="modal.mode === 'new' ? '— at least 8 characters' : '— leave blank to keep the current one'"></span>
            </label>
            <div class="flex gap-2">
              <input id="user-password" class="input flex-1 font-mono" :class="fieldError('password') && 'input-invalid'"
                     :type="showPassword ? 'text' : 'password'" x-model="modal.draft.password"
                     autocomplete="new-password">
              <button type="button" class="btn btn-secondary btn-sm !h-auto" @click="showPassword = !showPassword"
                      x-text="showPassword ? 'Hide' : 'Show'"></button>
              <button type="button" class="btn btn-secondary btn-sm !h-auto" @click="generate()">Generate</button>
            </div>
            <p class="field-error" x-show="fieldError('password')" x-text="fieldError('password')"></p>
            <p class="mt-1 text-[11px] text-neutral-500" x-show="modal.mode === 'edit' && !modal.draft.is_self && modal.draft.password">
              Setting a new password signs this person out of every open session.
            </p>
          </div>
        </div>

        <div class="modal-foot">
          <button type="button" class="btn btn-secondary" @click="closeModal()">Cancel</button>
          <button type="submit" class="btn btn-primary" :disabled="modal.saving"
                  x-text="modal.saving ? 'Saving…' : (modal.mode === 'new' ? 'Create user' : 'Save changes')"></button>
        </div>
      </form>
    </div>
  </template>

  {{-- delete confirmation --}}
  <template x-if="confirming">
    <div class="modal-backdrop" @click.self="cancelDelete()" @keydown.escape.window="cancelDelete()">
      <div class="modal w-[min(480px,100%)]">
        <div class="modal-head"><span class="h-2.5 w-2.5 bg-accent"></span> Delete user</div>
        <div class="modal-body">
          <p class="text-sm leading-relaxed text-neutral-700">
            Delete <strong class="text-ink" x-text="confirming.name"></strong>
            (<span x-text="confirming.email"></span>)? They are signed out at once and cannot sign in again.
            This cannot be undone.
          </p>

          <template x-if="ownsContent">
            <div class="field">
              <label for="user-heir">
                They created <span x-text="contentSummary(confirming)"></span>. Hand it to
              </label>
              <select id="user-heir" class="input" x-model="reassignTo">
                <template x-for="user in heirs" :key="user.id">
                  <option :value="String(user.id)" :selected="String(user.id) === reassignTo"
                          x-text="`${user.name} — ${user.email}`"></option>
                </template>
                <option value="">Nobody — keep it on the site with no author</option>
              </select>
            </div>
          </template>
        </div>
        <div class="modal-foot">
          <button class="btn btn-secondary" @click="cancelDelete()">Cancel</button>
          <button class="btn btn-primary" @click="confirmDelete()">Delete user</button>
        </div>
      </div>
    </div>
  </template>
</div>
</template>
