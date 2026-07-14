<template>
  <div class="side-panel-backdrop" @click="$emit('close')" />

  <div class="side-panel" role="dialog" aria-label="Task details">
    <!-- Header -->
    <div class="panel-header">
      <span class="dot" :style="{ background: project?.color }" />
      <span class="id mono">{{ task.key }}</span>

      <span v-if="locked" class="lock-pill">
        <LockIcon /> Sprint locked
      </span>

      <span v-if="task.completed" class="lock-pill done">
        <CheckIcon /> Completed
      </span>

      <DropdownMenu align="right">
        <template #trigger>
          <button type="button" class="btn ghost icon-only sm"><MoreIcon /></button>
        </template>
        <div>
          <MenuItem @click="copyId"><CopyIcon /> Copy ID</MenuItem>
          <MenuItem @click="copyLink"><LinkIcon /> Copy link</MenuItem>
          <div class="menu-divider" />
          <MenuItem danger :disabled="locked" @click="!locked && $emit('delete')">
            <TrashIcon /> Delete task
          </MenuItem>
        </div>
      </DropdownMenu>

      <button type="button" class="btn ghost icon-only sm" aria-label="Close" @click="$emit('close')">
        <CloseIcon />
      </button>
    </div>

    <!-- Body -->
    <div class="panel-body">
      <!-- Title -->
      <div
        ref="titleEl"
        class="panel-title"
        :contenteditable="!locked"
        spellcheck="false"
        data-placeholder="Task title"
        @blur="onTitleBlur"
      >{{ task.title }}</div>

      <!-- Not completed yet banner -->
      <div v-if="!task.completed" class="banner banner-done">
        <CheckIcon class="icon-done" />
        <span class="text" style="color: var(--status-done); font-weight: 500;">This task is not completed yet</span>
        <button type="button" class="btn primary sm" @click="$emit('complete')">Mark as completed</button>
      </div>

      <!-- Completed banner -->
      <div v-if="task.completed" class="banner banner-done">
        <CheckIcon class="icon-done" />
        <span class="text">
          Completed by <strong>{{ task.completed_by_user?.name ?? '—' }}</strong>
          <template v-if="completedAgo"> {{ completedAgo }}</template>.
        </span>
        <button type="button" class="btn secondary sm" @click="$emit('uncomplete')">
          Reopen
        </button>
      </div>

      <!-- Properties -->
      <div class="panel-section">
        <div class="props">
          <div class="key">Status</div>
          <div class="val">
            <DropdownMenu>
              <template #trigger>
                <span class="prop-pill">
                  <span class="dot" :style="{ background: currentColumn?.color }" />
                  <span>{{ currentColumn?.name ?? '—' }}</span>
                </span>
              </template>
              <div>
                <MenuItem
                  v-for="col in columns"
                  :key="col.id"
                  :disabled="locked"
                  @click="!locked && $emit('update', { board_column_id: col.id })"
                >
                  <span class="check-slot"><CheckIcon v-if="col.id === task.board_column_id" class="check" /></span>
                  <span class="dot" :style="{ background: col.color }" />
                  <span>{{ col.name }}</span>
                </MenuItem>
              </div>
            </DropdownMenu>
          </div>

          <div class="key">Assignees</div>
          <div class="val">
            <DropdownMenu :width="220">
              <template #trigger>
                <span class="prop-pill">
                  <template v-if="currentAssignees.length">
                    <span class="assignee-stack">
                      <span v-for="u in currentAssignees.slice(0, 3)" :key="u.id" class="stack-item">
                        <Avatar :name="u.name" size="sm" />
                      </span>
                      <span v-if="currentAssignees.length > 3" class="avatar-more">+{{ currentAssignees.length - 3 }}</span>
                    </span>
                    <span>{{ currentAssignees.length === 1 ? currentAssignees[0].name : `${currentAssignees.length} people` }}</span>
                  </template>
                  <template v-else>
                    <span class="avatar-empty">?</span>
                    <span class="muted">Unassigned</span>
                  </template>
                </span>
              </template>
              <div>
                <div class="menu-label">Assign to</div>
                <MenuItem :disabled="locked" data-keep-open @click="!locked && clearAssignees()">
                  <span class="check-slot"><CheckIcon v-if="!localAssigneeIds.length" class="check" /></span>
                  Unassigned
                </MenuItem>
                <div class="menu-divider" />
                <MenuItem
                  v-for="u in allUsers"
                  :key="u.id"
                  :disabled="locked"
                  data-keep-open
                  @click="!locked && toggleAssignee(u.id)"
                >
                  <span class="check-slot"><CheckIcon v-if="localAssigneeIds.includes(u.id)" class="check" /></span>
                  <Avatar :name="u.name" size="sm" />
                  <span>{{ u.name }}</span>
                </MenuItem>
              </div>
            </DropdownMenu>
          </div>

          <div class="key">Priority</div>
          <div class="val">
            <DropdownMenu>
              <template #trigger>
                <span class="prop-pill"><PriorityBadge :priority="task.priority" show-label /></span>
              </template>
              <div>
                <MenuItem
                  v-for="p in PRIORITIES"
                  :key="p.id"
                  :disabled="locked"
                  @click="!locked && $emit('update', { priority: p.id })"
                >
                  <span class="check-slot"><CheckIcon v-if="p.id === task.priority" class="check" /></span>
                  <PriorityBadge :priority="p.id" show-label />
                </MenuItem>
              </div>
            </DropdownMenu>
          </div>

          <div class="key">Project</div>
          <div class="val">
            <DropdownMenu :width="220">
              <template #trigger>
                <span class="prop-pill">
                  <span class="dot" :style="{ background: taskProject?.color }" />
                  <span>{{ taskProject?.name ?? '—' }}</span>
                </span>
              </template>
              <div>
                <div class="menu-label">Move to project</div>
                <MenuItem
                  v-for="p in allProjects"
                  :key="p.id"
                  :disabled="locked"
                  @click="!locked && $emit('update', { project_id: p.id })"
                >
                  <span class="check-slot"><CheckIcon v-if="p.id === (task.project_id ?? project?.id)" class="check" /></span>
                  <span class="dot" :style="{ background: p.color }" />
                  <span>{{ p.name }}</span>
                </MenuItem>
              </div>
            </DropdownMenu>
          </div>

          <div class="key">Sprint</div>
          <div class="val">
            <DropdownMenu :width="220">
              <template #trigger>
                <span class="prop-pill">
                  <LightningIcon class="dim" />
                  <span>{{ task.sprint?.name ?? '—' }}</span>
                </span>
              </template>
              <div>
                <div class="menu-label">Assign to sprint</div>
                <MenuItem
                  :disabled="locked"
                  @click="!locked && $emit('update', { sprint_id: null })"
                >
                  <span class="check-slot"><CheckIcon v-if="!task.sprint_id" class="check" /></span>
                  <span class="muted">No sprint (backlog)</span>
                </MenuItem>
                <div v-if="allSprints.length" class="menu-divider" />
                <MenuItem
                  v-for="s in allSprints"
                  :key="s.id"
                  :disabled="locked || s.locked"
                  @click="!locked && !s.locked && $emit('update', { sprint_id: s.id })"
                >
                  <span class="check-slot"><CheckIcon v-if="s.id === task.sprint_id" class="check" /></span>
                  <LightningIcon style="width:13px;height:13px;color:var(--fg-muted)" />
                  <span>{{ s.name }}</span>
                  <span v-if="s.locked" style="margin-left:auto;font-size:11px;color:var(--fg-subtle)">locked</span>
                </MenuItem>
              </div>
            </DropdownMenu>
          </div>

          <div class="key">Dates</div>
          <div class="val">
            <DropdownMenu :width="280">
              <template #trigger>
                <span class="prop-pill">
                  <CalendarIcon class="dim" />
                  <span v-if="task.start_date || task.due_date">
                    {{ dateRangeLabel }}
                  </span>
                  <span v-else class="muted">Set dates…</span>
                </span>
              </template>
              <div class="dropdown-pad" @click.stop>
                <div class="menu-label">Date range</div>
                <div class="date-fields">
                  <label class="field-block">
                    <span class="field-label">Start</span>
                    <input
                      type="date"
                      :value="dateValue(task.start_date)"
                      :disabled="locked"
                      class="input"
                      @change="e => !locked && $emit('update', { start_date: e.target.value || null })"
                    />
                  </label>
                  <label class="field-block">
                    <span class="field-label">Due</span>
                    <input
                      type="date"
                      :value="dateValue(task.due_date)"
                      :disabled="locked"
                      class="input"
                      @change="e => !locked && $emit('update', { due_date: e.target.value || null })"
                    />
                  </label>
                  <button
                    v-if="task.start_date || task.due_date"
                    type="button"
                    class="btn ghost sm clear-btn"
                    :disabled="locked"
                    @click="!locked && $emit('update', { start_date: null, due_date: null })"
                  >
                    <CloseIcon /> Clear dates
                  </button>
                </div>
              </div>
            </DropdownMenu>
          </div>

          <div class="key">Tags</div>
          <div class="val">
            <DropdownMenu :width="220">
              <template #trigger>
                <span class="prop-pill">
                  <span v-if="task.tags?.length" class="tags">
                    <span v-for="tag in task.tags" :key="tag" class="tag">{{ tag }}</span>
                  </span>
                  <span v-else class="muted">+ Add tags</span>
                </span>
              </template>
              <div>
                <div class="menu-label">Tags</div>
                <div class="dropdown-pad" @click.stop>
                  <input
                    v-model="tagSearch"
                    class="input"
                    autofocus
                    placeholder="Find or create a tag…"
                    @keydown.enter.prevent="addNewTag"
                    @keydown.stop
                  />
                </div>
                <MenuItem
                  v-if="canAddDraftTag"
                  :disabled="locked"
                  @click="!locked && addNewTag()"
                >
                  <span class="check-slot"><PlusIcon class="check" /></span>
                  <span>Create <strong>{{ normalizeTag(tagSearch) }}</strong></span>
                </MenuItem>
                <div v-if="filteredTagOptions.length === 0 && !canAddDraftTag" class="muted small-pad">No matches</div>
                <MenuItem
                  v-for="tag in filteredTagOptions"
                  :key="tag"
                  :disabled="locked"
                  data-keep-open
                  @click="!locked && toggleTag(tag)"
                >
                  <span class="check-slot"><CheckIcon v-if="task.tags?.includes(tag)" class="check" /></span>
                  <span>{{ tag }}</span>
                </MenuItem>
              </div>
            </DropdownMenu>
          </div>
        </div>
      </div>

      <!-- Subtasks -->
      <div class="panel-section">
        <div class="section-head">
          <div class="head-left">
            <span class="panel-section-title">Subtasks</span>
            <span v-if="task.subtasks?.length" class="count-mono">
              {{ completedSubtasksCount }}/{{ task.subtasks.length }}
            </span>
          </div>
          <button
            v-if="!locked"
            type="button"
            class="btn ghost sm"
            @click="openSubtaskInput"
          >+ Add</button>
        </div>

        <div v-if="task.subtasks?.length" class="subtask-progress">
          <div class="bar" :style="{ width: subtaskProgress + '%' }" />
        </div>

        <p v-if="!task.subtasks?.length && !showSubtaskInput" class="muted small-pad">No subtasks yet.</p>

        <div v-if="task.subtasks?.length" class="subtask-list">
          <div
            v-for="sub in task.subtasks"
            :key="sub.id"
            class="subtask-row"
            @mouseenter="hoveredSubtaskId = sub.id"
            @mouseleave="hoveredSubtaskId = null"
          >
            <button
              type="button"
              class="subtask-check"
              :class="{ done: sub.completed }"
              :disabled="locked"
              @click.stop="$emit('subtaskToggle', sub.id, !sub.completed)"
            >
              <CheckIcon v-if="sub.completed" />
            </button>
            <span
              class="subtask-title"
              :class="{ done: sub.completed }"
              @click="subtaskPath = [sub.id]"
            >{{ sub.title }}</span>
            <div class="subtask-chips">
              <span
                v-if="sub.subtasks?.length"
                class="subtask-count"
                :title="`${sub.subtasks.length} subtasks`"
                @click="subtaskPath = [sub.id]"
              >
                <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                {{ subtaskChildCount(sub) }}
              </span>
              <span
                v-if="sub.due_date"
                class="subtask-due"
                :class="{ urgent: subDueInfo(sub).urgent }"
              >{{ subDueInfo(sub).label }}</span>
              <span
                v-if="sub.priority && sub.priority !== 'med'"
                class="subtask-prio"
                :style="{ background: priorityColor(sub.priority) }"
                :title="sub.priority"
              />
              <Avatar v-if="sub.assignee" :name="sub.assignee.name" size="sm" />
            </div>
            <button
              v-if="!locked && hoveredSubtaskId === sub.id"
              type="button"
              class="subtask-remove"
              :aria-label="`Remove ${sub.title}`"
              @click.stop="$emit('subtaskRemove', sub.id)"
            ><CloseIcon /></button>
          </div>
        </div>

        <div v-if="showSubtaskInput" class="subtask-add-row">
          <div class="subtask-check empty" />
          <input
            ref="subtaskInputEl"
            v-model="newSubtaskTitle"
            class="input"
            placeholder="Subtask title… (Enter to save)"
            @keydown.enter.prevent="submitSubtask"
            @keydown.escape="cancelSubtask"
            @blur="onSubtaskBlur"
          />
        </div>
      </div>

      <!-- Description -->
      <div class="panel-section">
        <div class="panel-section-title">Description</div>
        <div
          ref="descEl"
          class="description"
          :class="{ empty: !task.description }"
          :contenteditable="!locked"
          spellcheck="false"
          @blur="onDescBlur"
        >{{ task.description || '' }}</div>
      </div>

      <!-- Attachments -->
      <AttachmentsSection
        :attachments="task.attachments ?? []"
        :locked="locked"
        @upload="file => emit('attachmentUpload', file)"
        @remove="id => emit('attachmentRemove', id)"
      />

      <!-- Participants -->
      <div class="panel-section">
        <div class="section-head">
          <div class="head-left">
            <span class="panel-section-title">Participants</span>
            <span v-if="participants.length" class="count-mono">{{ participants.length }}</span>
          </div>
          <button v-if="participants.length" type="button" class="btn ghost sm" @click="showParticipantsModal = true">View all</button>
        </div>
        <div v-if="participants.length" class="participants-row">
          <button
            v-for="p in participants.slice(0, 8)"
            :key="p.id"
            type="button"
            class="participant-avatar-btn"
            :title="p.name"
            @click="showParticipantsModal = true"
          >
            <Avatar :name="p.name" size="sm" />
          </button>
          <span v-if="participants.length > 8" class="avatar-more">+{{ participants.length - 8 }}</span>
        </div>
        <p v-else class="muted small-pad">No participants yet.</p>
      </div>

      <Teleport to="body">
        <template v-if="showParticipantsModal">
          <div class="tp-modal-backdrop" @click="showParticipantsModal = false" />
          <div class="tp-modal" role="dialog" aria-label="Participants">
            <div class="tp-modal-header">
              <span class="tp-modal-title">Participants</span>
              <button type="button" class="btn ghost icon-only sm" @click="showParticipantsModal = false"><CloseIcon /></button>
            </div>
            <div class="tp-modal-body">
              <p v-if="!participants.length" class="muted small-pad">No participants yet.</p>
              <div v-for="p in participants" :key="p.id" class="participant-row-item">
                <Avatar :name="p.name" size="md" />
                <div class="participant-meta">
                  <div class="participant-name">{{ p.name }}</div>
                  <div class="participant-roles">
                    <span v-for="role in p.roles" :key="role" :class="['role-chip', `role-${role}`]">{{ role }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </Teleport>

      <!-- Access requests -->
      <div v-if="accessRequests.length" class="panel-section">
        <div class="section-head">
          <div class="head-left">
            <span class="panel-section-title">Access requests</span>
            <span class="count-mono">{{ accessRequests.length }}</span>
          </div>
        </div>

        <div class="access-req-list">
          <div
            v-for="req in accessRequests"
            :key="req.id"
            :class="['access-req', resolvingAccess[req.id] ? 'is-' + resolvingAccess[req.id] : '']"
          >
            <Avatar :name="req.user?.name" size="md" />
            <div class="access-req-body">
              <div class="access-req-top">
                <span class="access-req-name">{{ req.user?.name }}</span>
                <span class="access-req-time">{{ req.requested_at }}</span>
              </div>
              <div class="access-req-sub">wants access to this task</div>
              <div v-if="req.message" class="access-req-msg">{{ req.message }}</div>

              <div v-if="resolvingAccess[req.id]" :class="['access-req-resolved', resolvingAccess[req.id]]">
                <template v-if="resolvingAccess[req.id] === 'approved'"><CheckIcon /> Access granted</template>
                <template v-else><CloseIcon /> Request declined</template>
              </div>
              <div v-else-if="canManageAccess" class="access-req-actions">
                <button type="button" class="btn primary sm" @click="resolveAccess(req, true)"><CheckIcon /> Approve</button>
                <button type="button" class="btn secondary sm" @click="resolveAccess(req, false)"><CloseIcon /> Decline</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs: Comments / Activity -->
      <div class="panel-section">
        <div class="minitabs">
          <button
            type="button"
            class="minitab"
            :class="{ active: activeTab === 'comments' }"
            @click="activeTab = 'comments'"
          >
            Comments <span class="badge">{{ task.comments?.length ?? 0 }}</span>
          </button>
          <button
            type="button"
            class="minitab"
            :class="{ active: activeTab === 'activity' }"
            @click="activeTab = 'activity'"
          >
            Activity <span class="badge">{{ task.activities?.length ?? 0 }}</span>
          </button>
        </div>

        <!-- Comments tab -->
        <div v-if="activeTab === 'comments'" class="vstack">
          <div v-if="!task.comments?.length" class="muted small-pad">
            No comments yet. Start the discussion.
          </div>

          <div v-for="c in task.comments" :key="c.id">
            <div class="comment">
              <Avatar :name="c.user?.name" size="sm" />
              <div class="body">
                <div class="author-row">
                  <span class="author">{{ c.user?.name }}</span>
                  <span class="time">{{ formatAgo(c.created_at) }}</span>
                </div>
                <div class="text" v-html="renderCommentBody(c.body)" />
                <div v-if="!locked" class="actions">
                  <button type="button" @click="replyingTo = replyingTo === c.id ? null : c.id">Reply</button>
                </div>
              </div>
            </div>

            <div v-if="c.replies?.length || replyingTo === c.id" class="thread-replies">
              <div v-for="r in c.replies" :key="r.id" class="comment">
                <Avatar :name="r.user?.name" size="sm" />
                <div class="body">
                  <div class="author-row">
                    <span class="author">{{ r.user?.name }}</span>
                    <span class="time">{{ formatAgo(r.created_at) }}</span>
                  </div>
                  <div class="text" v-html="renderCommentBody(r.body)" />
                </div>
              </div>

              <div v-if="replyingTo === c.id" class="composer">
                <Avatar :name="currentUser?.name" size="sm" />
                <div class="body">
                  <MentionTextarea
                    v-model="replyText"
                    :users="mentionableUsers"
                    :placeholder="`Reply to ${c.user?.name ?? ''}…`"
                  />
                  <div class="hstack-end">
                    <button type="button" class="btn ghost sm" @click="replyingTo = null; replyText = ''">Cancel</button>
                    <button
                      type="button"
                      class="btn primary sm"
                      :disabled="!replyText.trim()"
                      @click="submitReply(c.id)"
                    >Reply</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Composer -->
          <div v-if="!locked" class="composer">
            <Avatar :name="currentUser?.name" size="sm" />
            <div class="body">
              <MentionTextarea
                v-model="newComment"
                :users="mentionableUsers"
                placeholder="Add a comment… (@ to mention)"
              />
              <div class="send-row">
                <span class="subtle small">
                  Markdown supported · <span class="kbd">⌘↵</span> to send
                </span>
                <button
                  type="button"
                  class="btn primary"
                  :disabled="!newComment.trim()"
                  @click="submitComment"
                >
                  <SendIcon /> Comment
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Activity tab -->
        <div v-if="activeTab === 'activity'" class="vstack-tight">
          <div v-if="!task.activities?.length" class="muted small-pad">No activity yet.</div>
          <div
            v-for="(a, i) in [...(task.activities ?? [])].reverse()"
            :key="a.id ?? i"
            class="audit-row"
          >
            <div class="dot-col">
              <div class="line" />
              <div class="dot" />
            </div>
            <div class="text">
              <strong>{{ a.user?.name ?? 'Someone' }}</strong>
              <span v-html="activityMessage(a)" />
            </div>
            <div class="time">{{ formatAgo(a.created_at) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Subtask detail panel — slides over the task panel, design 1:1 -->
  <template v-if="openSubtask">
    <div class="side-panel-backdrop subtask-layer" @click="closeSubtaskPanel" />
    <div class="side-panel subtask-layer" role="dialog" aria-label="Subtask details">
      <div class="panel-header">
        <button type="button" class="btn ghost sm crumb" @click="goBackSubtask">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          <span class="crumb-text">{{ subtaskParentTitle }}</span>
        </button>
        <div class="spacer" />
        <button type="button" class="btn ghost icon-only sm" aria-label="Close" @click="closeSubtaskPanel"><CloseIcon /></button>
      </div>

      <div class="panel-body">
        <div class="subtask-kicker">
          <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          <span>Subtask</span>
        </div>

        <div
          ref="subtaskTitleEl"
          :key="openSubtask.id"
          class="panel-title"
          :contenteditable="!locked"
          spellcheck="false"
          data-placeholder="Subtask title"
          @blur="onSubtaskTitleBlur"
          @keydown.enter.prevent="onSubtaskTitleEnter"
        >{{ openSubtask.title }}</div>

        <div class="panel-section">
          <div class="props">
            <!-- Assignees -->
            <div class="key">Assignees</div>
            <div class="val">
              <DropdownMenu :width="220">
                <template #trigger>
                  <span class="prop-pill">
                    <template v-if="currentSubtaskAssignees.length">
                      <span class="assignee-stack">
                        <span v-for="u in currentSubtaskAssignees.slice(0, 3)" :key="u.id" class="stack-item">
                          <Avatar :name="u.name" size="sm" />
                        </span>
                        <span v-if="currentSubtaskAssignees.length > 3" class="avatar-more">+{{ currentSubtaskAssignees.length - 3 }}</span>
                      </span>
                      <span>{{ currentSubtaskAssignees.length === 1 ? currentSubtaskAssignees[0].name : `${currentSubtaskAssignees.length} people` }}</span>
                    </template>
                    <template v-else>
                      <span class="avatar-empty">?</span>
                      <span class="muted">Unassigned</span>
                    </template>
                  </span>
                </template>
                <div>
                  <div class="menu-label">Assign to</div>
                  <MenuItem :disabled="locked" data-keep-open @click="!locked && subtaskClearAssignees()">
                    <span class="check-slot"><CheckIcon v-if="!localSubtaskAssigneeIds.length" class="check" /></span>
                    Unassigned
                  </MenuItem>
                  <div class="menu-divider" />
                  <MenuItem
                    v-for="u in allUsers"
                    :key="u.id"
                    :disabled="locked"
                    data-keep-open
                    @click="!locked && subtaskToggleAssignee(u.id)"
                  >
                    <span class="check-slot"><CheckIcon v-if="localSubtaskAssigneeIds.includes(u.id)" class="check" /></span>
                    <Avatar :name="u.name" size="sm" />
                    <span>{{ u.name }}</span>
                  </MenuItem>
                </div>
              </DropdownMenu>
            </div>

            <!-- Priority -->
            <div class="key">Priority</div>
            <div class="val">
              <DropdownMenu>
                <template #trigger>
                  <span class="prop-pill"><PriorityBadge :priority="openSubtask.priority || 'med'" show-label /></span>
                </template>
                <div>
                  <MenuItem
                    v-for="p in PRIORITIES"
                    :key="p.id"
                    :disabled="locked"
                    @click="!locked && emit('subtaskUpdate', openSubtask.id, { priority: p.id })"
                  >
                    <span class="check-slot"><CheckIcon v-if="p.id === (openSubtask.priority || 'med')" class="check" /></span>
                    <PriorityBadge :priority="p.id" show-label />
                  </MenuItem>
                </div>
              </DropdownMenu>
            </div>

            <!-- Dates -->
            <div class="key">Dates</div>
            <div class="val">
              <DropdownMenu :width="280">
                <template #trigger>
                  <span class="prop-pill">
                    <CalendarIcon class="dim" />
                    <span v-if="openSubtask.start_date || openSubtask.due_date">
                      {{ subtaskDateRangeLabel }}
                    </span>
                    <span v-else class="muted">Set dates…</span>
                  </span>
                </template>
                <div class="dropdown-pad" @click.stop>
                  <div class="menu-label">Date range</div>
                  <div class="date-fields">
                    <label class="field-block">
                      <span class="field-label">Start</span>
                      <input
                        type="date"
                        :value="dateValue(openSubtask.start_date)"
                        :disabled="locked"
                        class="input"
                        @change="e => !locked && emit('subtaskUpdate', openSubtask.id, { start_date: e.target.value || null })"
                      />
                    </label>
                    <label class="field-block">
                      <span class="field-label">Due</span>
                      <input
                        type="date"
                        :value="dateValue(openSubtask.due_date)"
                        :disabled="locked"
                        class="input"
                        @change="e => !locked && emit('subtaskUpdate', openSubtask.id, { due_date: e.target.value || null })"
                      />
                    </label>
                    <button
                      v-if="openSubtask.start_date || openSubtask.due_date"
                      type="button"
                      class="btn ghost sm clear-btn"
                      :disabled="locked"
                      @click="!locked && emit('subtaskUpdate', openSubtask.id, { start_date: null, due_date: null })"
                    >
                      <CloseIcon /> Clear dates
                    </button>
                  </div>
                </div>
              </DropdownMenu>
            </div>

            <!-- Tags -->
            <div class="key">Tags</div>
            <div class="val">
              <DropdownMenu :width="220" keep-open>
                <template #trigger>
                  <span class="prop-pill">
                    <span v-if="openSubtask.tags?.length" class="tags">
                      <span v-for="t in openSubtask.tags" :key="t" class="tag">{{ t }}</span>
                    </span>
                    <span v-else class="muted">+ Add tags</span>
                  </span>
                </template>
                <div>
                  <div class="menu-label">Tags</div>
                  <div class="dropdown-pad" @click.stop>
                    <input
                      v-model="subtaskTagSearch"
                      class="input"
                      autofocus
                      placeholder="Find or create a tag…"
                      @keydown.enter.prevent="subtaskAddNewTag"
                      @keydown.stop
                    />
                  </div>
                  <MenuItem
                    v-if="subtaskCanAddDraftTag"
                    :disabled="locked"
                    @click="!locked && subtaskAddNewTag()"
                  >
                    <span class="check-slot"><PlusIcon class="check" /></span>
                    <span>Create <strong>{{ normalizeTag(subtaskTagSearch) }}</strong></span>
                  </MenuItem>
                  <div v-if="subtaskFilteredTagOptions.length === 0 && !subtaskCanAddDraftTag" class="muted small-pad">No matches</div>
                  <MenuItem
                    v-for="tag in subtaskFilteredTagOptions"
                    :key="tag"
                    :disabled="locked"
                    data-keep-open
                    @click="!locked && subtaskToggleTag(tag)"
                  >
                    <span class="check-slot"><CheckIcon v-if="openSubtask.tags?.includes(tag)" class="check" /></span>
                    <span>{{ tag }}</span>
                  </MenuItem>
                </div>
              </DropdownMenu>
            </div>
          </div>
        </div>

        <!-- Description -->
        <div class="panel-section">
          <div class="panel-section-title">Description</div>
          <div
            ref="subtaskDescEl"
            class="description"
            :class="{ empty: !openSubtask.description }"
            :contenteditable="!locked"
            spellcheck="false"
            @blur="onSubtaskDescBlur"
          >{{ openSubtask.description || '' }}</div>
        </div>

        <!-- Subtask Attachments -->
        <AttachmentsSection
          :attachments="openSubtaskAttachments"
          :locked="locked"
          @upload="onSubtaskAttachmentUpload"
          @remove="onSubtaskAttachmentRemove"
        />

        <!-- Nested subtasks — subtasks of this subtask (design 1:1) -->
        <div class="panel-section">
          <div class="section-head">
            <div class="head-left">
              <span class="panel-section-title">Subtasks</span>
              <span v-if="openSubtaskChildren.length" class="count-mono">
                {{ nestedDoneCount }}/{{ openSubtaskChildren.length }}
              </span>
            </div>
            <button
              v-if="!locked"
              type="button"
              class="btn ghost sm"
              @click="openNestedSubtaskInput"
            >+ Add</button>
          </div>

          <div v-if="openSubtaskChildren.length" class="subtask-progress">
            <div class="bar" :style="{ width: nestedProgress + '%' }" />
          </div>

          <p v-if="!openSubtaskChildren.length && !showNestedSubtaskInput" class="muted small-pad">No subtasks yet.</p>

          <div v-if="openSubtaskChildren.length" class="subtask-list">
            <div
              v-for="sub in openSubtaskChildren"
              :key="sub.id"
              class="subtask-row"
              @mouseenter="hoveredNestedId = sub.id"
              @mouseleave="hoveredNestedId = null"
            >
              <button
                type="button"
                class="subtask-check"
                :class="{ done: sub.completed }"
                :disabled="locked"
                @click.stop="$emit('subtaskToggle', sub.id, !sub.completed)"
              >
                <CheckIcon v-if="sub.completed" />
              </button>
              <span
                class="subtask-title"
                :class="{ done: sub.completed }"
                @click="drillIntoSubtask(sub.id)"
              >{{ sub.title }}</span>
              <div class="subtask-chips">
                <span
                  v-if="sub.subtasks?.length"
                  class="subtask-count"
                  :title="`${sub.subtasks.length} subtasks`"
                  @click="drillIntoSubtask(sub.id)"
                >
                  <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                  {{ subtaskChildCount(sub) }}
                </span>
                <span
                  v-if="sub.due_date"
                  class="subtask-due"
                  :class="{ urgent: subDueInfo(sub).urgent }"
                >{{ subDueInfo(sub).label }}</span>
                <span
                  v-if="sub.priority && sub.priority !== 'med'"
                  class="subtask-prio"
                  :style="{ background: priorityColor(sub.priority) }"
                  :title="sub.priority"
                />
                <Avatar v-if="sub.assignee" :name="sub.assignee.name" size="sm" />
              </div>
              <button
                v-if="!locked && hoveredNestedId === sub.id"
                type="button"
                class="subtask-remove"
                :aria-label="`Remove ${sub.title}`"
                @click.stop="$emit('subtaskRemove', sub.id)"
              ><CloseIcon /></button>
            </div>
          </div>

          <div v-if="showNestedSubtaskInput" class="subtask-add-row">
            <div class="subtask-check empty" />
            <input
              ref="nestedSubtaskInputEl"
              v-model="newNestedSubtaskTitle"
              class="input"
              placeholder="Subtask title… (Enter to save)"
              @keydown.enter.prevent="submitNestedSubtask"
              @keydown.escape="cancelNestedSubtask"
              @blur="onNestedSubtaskBlur"
            />
          </div>
        </div>

        <!-- Subtask Comments -->
        <div class="panel-section">
          <div class="minitabs">
            <button type="button" class="minitab active">
              Comments <span class="badge">{{ openSubtaskComments.length }}</span>
            </button>
          </div>
          <div class="vstack">
            <p v-if="!openSubtaskComments.length" class="muted small-pad">No comments yet.</p>
            <div v-for="c in openSubtaskComments" :key="c.id">
              <div class="comment">
                <Avatar :name="c.author ?? c.user?.name" size="sm" />
                <div class="body">
                  <div class="author-row">
                    <span class="author">{{ c.author ?? c.user?.name }}</span>
                    <span class="time">{{ c.time ?? formatAgo(c.created_at) }}</span>
                  </div>
                  <div class="text" v-html="renderCommentBody(c.body)" />
                  <div v-if="!locked" class="actions">
                    <button type="button" @click="subtaskReplyingTo = subtaskReplyingTo === c.id ? null : c.id">Reply</button>
                  </div>
                </div>
              </div>

              <div v-if="c.replies?.length || subtaskReplyingTo === c.id" class="thread-replies">
                <div v-for="r in c.replies" :key="r.id" class="comment">
                  <Avatar :name="r.user?.name" size="sm" />
                  <div class="body">
                    <div class="author-row">
                      <span class="author">{{ r.user?.name }}</span>
                      <span class="time">{{ formatAgo(r.created_at) }}</span>
                    </div>
                    <div class="text" v-html="renderCommentBody(r.body)" />
                  </div>
                </div>

                <div v-if="subtaskReplyingTo === c.id" class="composer">
                  <Avatar :name="currentUser?.name" size="sm" />
                  <div class="body">
                    <MentionTextarea
                      v-model="subtaskReplyText"
                      :users="mentionableUsers"
                      :placeholder="`Reply to ${c.user?.name ?? ''}…`"
                    />
                    <div class="hstack-end">
                      <button type="button" class="btn ghost sm" @click="subtaskReplyingTo = null; subtaskReplyText = ''">Cancel</button>
                      <button
                        type="button"
                        class="btn primary sm"
                        :disabled="!subtaskReplyText.trim()"
                        @click="submitSubtaskReply(c.id)"
                      >Reply</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-if="!locked" class="composer">
              <Avatar :name="currentUser?.name" size="sm" />
              <div class="body">
                <MentionTextarea
                  v-model="subtaskNewComment"
                  :users="mentionableUsers"
                  placeholder="Add a comment… (@ to mention)"
                />
                <div class="hstack-end">
                  <button
                    type="button"
                    class="btn primary sm"
                    :disabled="!subtaskNewComment.trim()"
                    @click="submitSubtaskComment"
                  >Comment</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup>
import { ref, computed, reactive, watch, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { useToast } from '@/composables/useToast'
import { copyText } from '@/utils/clipboard'
import Avatar from '@/Components/UI/Avatar.vue'
import PriorityBadge from '@/Components/UI/PriorityBadge.vue'
import DropdownMenu from '@/Components/UI/DropdownMenu.vue'
import MenuItem from '@/Components/UI/MenuItem.vue'
import AttachmentsSection from '@/Components/Task/AttachmentsSection.vue'
import MentionTextarea from '@/Components/Task/MentionTextarea.vue'
import {
  LockIcon, CheckIcon, MoreIcon, CopyIcon, LinkIcon, TrashIcon,
  CloseIcon, LightningIcon, SendIcon, CalendarIcon, PlusIcon,
} from '@/Components/UI/Icons.vue'
import { formatDueDate } from '@/utils/dueDate'

const props = defineProps({
  task:        { type: Object,  required: true },
  columns:     { type: Array,   default: () => [] },
  allUsers:    { type: Array,   default: () => [] },
  allProjects: { type: Array,   default: () => [] },
  allSprints:  { type: Array,   default: () => [] },
  allTags:     { type: Array,   default: () => [] },
  project:     { type: Object,  default: null },
  locked:      { type: Boolean, default: false },
})
const emit = defineEmits([
  'close', 'update', 'comment', 'reply',
  'complete', 'uncomplete', 'delete',
  'subtask', 'subtaskToggle', 'subtaskRemove', 'subtaskUpdate', 'subtaskComment', 'subtaskReply',
  'attachmentUpload', 'attachmentRemove',
])

const hoveredSubtaskId = ref(null)
const hoveredNestedId  = ref(null)

// Drill stack of subtask ids from the root task down to the currently open
// subtask. A single id means "a direct child of the root is open"; pushing more
// ids descends into subtasks-of-subtasks to any depth (design 1:1).
const subtaskPath = ref([])

// Walk the loaded subtask tree following the id path; returns the deepest node
// (or null if any link is missing, e.g. after a delete).
function resolveSubtaskPath(ids) {
  let nodes = props.task.subtasks || []
  let node = null
  for (const id of ids) {
    node = nodes.find(s => s.id === id) || null
    if (!node) return null
    nodes = node.subtasks || []
  }
  return node
}

const openSubtask = computed(() => subtaskPath.value.length ? resolveSubtaskPath(subtaskPath.value) : null)

// One level up from the open subtask: its parent subtask, or the root task when
// the open subtask is a direct child of the root. Drives the back crumb.
const openSubtaskParent = computed(() =>
  subtaskPath.value.length <= 1 ? props.task : resolveSubtaskPath(subtaskPath.value.slice(0, -1))
)
const subtaskParentTitle  = computed(() => openSubtaskParent.value?.title || props.task.title)
const openSubtaskChildren = computed(() => openSubtask.value?.subtasks || [])
const nestedDoneCount     = computed(() => openSubtaskChildren.value.filter(s => s.completed).length)
const nestedProgress      = computed(() => {
  const total = openSubtaskChildren.value.length
  return total === 0 ? 0 : Math.round((nestedDoneCount.value / total) * 100)
})

function drillIntoSubtask(id) { subtaskPath.value = [...subtaskPath.value, id] }
function goBackSubtask()       { subtaskPath.value = subtaskPath.value.slice(0, -1) }
function closeSubtaskPanel()   { subtaskPath.value = [] }
function subtaskChildCount(sub) {
  const direct = sub.subtasks?.length ?? 0
  const done = sub.subtasks?.filter(s => s.completed).length ?? 0
  return `${done}/${direct}`
}

const taskProject = computed(() =>
  props.allProjects.find(p => p.id === (props.task.project_id ?? props.project?.id)) ?? props.project
)

const PRIORITY_COLORS = {
  urgent: '#dc2626',
  high:   '#d97706',
  med:    '#94948c',
  low:    '#6b7280',
}
function priorityColor(id) { return PRIORITY_COLORS[id] || PRIORITY_COLORS.med }
function subDueInfo(sub) {
  return formatDueDate(sub.due_date, null, sub.completed)
}

const page = usePage()
const currentUser = computed(() => page.props.auth.user)

// Workspace/project members minus the current user — feeds the @-mention picker.
const mentionableUsers = computed(() => {
  const me = currentUser.value?.id
  return (props.allUsers ?? []).filter(u => u.id !== me)
})

const activeTab        = ref('comments')
const newComment       = ref('')
const replyText        = ref('')
const replyingTo       = ref(null)
const titleEl          = ref(null)
const descEl           = ref(null)
const showSubtaskInput = ref(false)
const newSubtaskTitle  = ref('')
const subtaskInputEl   = ref(null)
const showNestedSubtaskInput = ref(false)
const newNestedSubtaskTitle  = ref('')
const nestedSubtaskInputEl   = ref(null)
const tagSearch        = ref('')

const PRIORITIES = [
  { id: 'urgent', label: 'Urgent' },
  { id: 'high',   label: 'High' },
  { id: 'med',    label: 'Medium' },
  { id: 'low',    label: 'Low' },
]
const ALL_TAGS = ['frontend', 'backend', 'design', 'bug', 'feature', 'infra', 'research', 'a11y', 'perf']

const allTagOptions = computed(() => {
  const extra = props.task.tags ?? []
  return [...new Set([...ALL_TAGS, ...props.allTags, ...extra])]
})
const filteredTagOptions = computed(() => {
  const q = tagSearch.value.trim().toLowerCase()
  if (!q) return allTagOptions.value
  return allTagOptions.value.filter(t => t.includes(q))
})
const canAddDraftTag = computed(() => {
  const n = normalizeTag(tagSearch.value)
  return n && !allTagOptions.value.includes(n)
})

function normalizeTag(s) { return s.trim().toLowerCase().replace(/\s+/g, '-') }
function addNewTag() {
  const t = normalizeTag(tagSearch.value)
  if (!t) return
  if (!props.task.tags?.includes(t)) emit('update', { tags: [...(props.task.tags ?? []), t] })
  tagSearch.value = ''
}
function toggleTag(tag) {
  const tags = [...(props.task.tags ?? [])]
  const idx  = tags.indexOf(tag)
  if (idx === -1) tags.push(tag); else tags.splice(idx, 1)
  emit('update', { tags })
}

const subtaskTagSearch = ref('')
const subtaskDescEl    = ref(null)
const subtaskTitleEl   = ref(null)

// Multi-assignee for the open subtask
const localSubtaskAssigneeIds = ref([])
watch(openSubtask, (s) => {
  localSubtaskAssigneeIds.value = s?.assignees?.map(u => u.id) ?? (s?.assignee_id ? [s.assignee_id] : [])
}, { immediate: true })
const currentSubtaskAssignees = computed(() =>
  localSubtaskAssigneeIds.value.map(id => props.allUsers.find(u => u.id === id)).filter(Boolean)
)
function subtaskToggleAssignee(id) {
  if (!openSubtask.value) return
  const idx = localSubtaskAssigneeIds.value.indexOf(id)
  if (idx === -1) localSubtaskAssigneeIds.value = [...localSubtaskAssigneeIds.value, id]
  else localSubtaskAssigneeIds.value = localSubtaskAssigneeIds.value.filter(i => i !== id)
  emit('subtaskUpdate', openSubtask.value.id, { assignee_ids: localSubtaskAssigneeIds.value })
}
function subtaskClearAssignees() {
  if (!openSubtask.value) return
  localSubtaskAssigneeIds.value = []
  emit('subtaskUpdate', openSubtask.value.id, { assignee_ids: [] })
}

const subtaskDateRangeLabel = computed(() => {
  const s = openSubtask.value?.start_date, d = openSubtask.value?.due_date
  if (s && d && s !== d) return `${formatDate(s)} → ${formatDate(d)}`
  return formatDate(d || s)
})

function onSubtaskTitleBlur(e) {
  if (!openSubtask.value) return
  const val = e.target.innerText.trim()
  if (val && val !== openSubtask.value.title) {
    emit('subtaskUpdate', openSubtask.value.id, { title: val })
  } else if (!val && subtaskTitleEl.value) {
    subtaskTitleEl.value.innerText = openSubtask.value.title
  }
}
function onSubtaskTitleEnter(e) {
  e.target.blur()
}

// Multi-assignee
const localAssigneeIds = ref(
  props.task.assignees?.map(u => u.id) ?? (props.task.assignee_id ? [props.task.assignee_id] : [])
)
watch(() => props.task, t => {
  localAssigneeIds.value = t.assignees?.map(u => u.id) ?? (t.assignee_id ? [t.assignee_id] : [])
})
const currentAssignees = computed(() =>
  localAssigneeIds.value.map(id => props.allUsers.find(u => u.id === id)).filter(Boolean)
)
function toggleAssignee(id) {
  const idx = localAssigneeIds.value.indexOf(id)
  if (idx === -1) localAssigneeIds.value = [...localAssigneeIds.value, id]
  else localAssigneeIds.value = localAssigneeIds.value.filter(i => i !== id)
  emit('update', { assignee_ids: localAssigneeIds.value })
}
function clearAssignees() {
  localAssigneeIds.value = []
  emit('update', { assignee_ids: [] })
}

// Participants — canonical list comes from the server (/tasks/{id}/participants)
const showParticipantsModal = ref(false)
const participants = ref([])
async function loadParticipants() {
  if (!props.task?.id) { participants.value = []; return }
  try {
    const res = await fetch(route('tasks.participants', props.task.uuid), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    if (!res.ok) { participants.value = []; return }
    participants.value = await res.json()
  } catch {
    participants.value = []
  }
}
loadParticipants()
watch(() => props.task?.id, loadParticipants)
watch(() => [
  props.task?.assignees?.length,
  props.task?.assignee_id,
  props.task?.comments?.length,
  (props.task?.comments ?? []).reduce((n, c) => n + (c.replies?.length ?? 0), 0),
  props.task?.audit_logs?.length,
  props.task?.completed_by,
], () => loadParticipants())

// Access requests — pending requests from people who want into this task,
// plus whether the current user (owner/admin) may approve or decline them.
const { toast } = useToast()
const accessRequests   = ref([])
const canManageAccess  = ref(false)
const resolvingAccess  = reactive({}) // { [id]: 'approved' | 'declined' }

async function loadAccessRequests() {
  if (!props.task?.id) { accessRequests.value = []; canManageAccess.value = false; return }
  try {
    const res = await fetch(route('tasks.access-requests.index', props.task.uuid), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    if (!res.ok) { accessRequests.value = []; canManageAccess.value = false; return }
    const data = await res.json()
    accessRequests.value  = data.requests ?? []
    canManageAccess.value = !!data.can_manage
  } catch {
    accessRequests.value = []
    canManageAccess.value = false
  }
}
loadAccessRequests()
watch(() => props.task?.id, loadAccessRequests)

async function resolveAccess(req, approved) {
  if (!canManageAccess.value || resolvingAccess[req.id]) return
  resolvingAccess[req.id] = approved ? 'approved' : 'declined'
  const name = req.user?.name || 'them'
  try {
    const action = approved ? 'tasks.access-requests.approve' : 'tasks.access-requests.decline'
    await axios.post(route(action, [props.task.uuid, req.id]))
    // Brief resolved state reads before we drop the row.
    setTimeout(() => {
      accessRequests.value = accessRequests.value.filter(r => r.id !== req.id)
      delete resolvingAccess[req.id]
    }, 280)
    toast(approved ? `Granted ${name} access to this task` : `Declined ${name}'s access request`)
    if (approved) loadParticipants()
  } catch (e) {
    delete resolvingAccess[req.id]
    toast(e.response?.data?.message || 'Could not update request')
  }
}

const openSubtaskAttachments = computed(() => openSubtask.value?.attachments ?? [])
const openSubtaskComments = computed(() => openSubtask.value?.comments ?? [])
const subtaskNewComment = ref('')
function onSubtaskAttachmentUpload(file) {
  if (!openSubtask.value) return
  emit('attachmentUpload', file, openSubtask.value.id)
}
function onSubtaskAttachmentRemove(id) {
  emit('attachmentRemove', id)
}
function submitSubtaskComment() {
  const body = subtaskNewComment.value.trim()
  if (!body || !openSubtask.value) return
  // A subtask is a Task, so its comments persist through the same
  // tasks.comments.store route (which broadcasts CommentAdded). The parent
  // handles the POST + refetch so the new comment lands on the subtask.
  emit('subtaskComment', openSubtask.value.id, body)
  subtaskNewComment.value = ''
}

const subtaskReplyingTo = ref(null)
const subtaskReplyText  = ref('')
function submitSubtaskReply(commentId) {
  const body = subtaskReplyText.value.trim()
  if (!body || !openSubtask.value) return
  // Replies route through tasks.comments.reply with the subtask's id (a subtask
  // IS a Task), so ReplyAdded broadcasts just like a top-level comment reply.
  emit('subtaskReply', openSubtask.value.id, commentId, body)
  subtaskReplyText.value = ''
  subtaskReplyingTo.value = null
}

const subtaskAllTagOptions = computed(() => {
  const extra = openSubtask.value?.tags ?? []
  return [...new Set([...ALL_TAGS, ...props.allTags, ...extra])]
})
const subtaskFilteredTagOptions = computed(() => {
  const q = subtaskTagSearch.value.trim().toLowerCase()
  if (!q) return subtaskAllTagOptions.value
  return subtaskAllTagOptions.value.filter(t => t.includes(q))
})
const subtaskCanAddDraftTag = computed(() => {
  const n = normalizeTag(subtaskTagSearch.value)
  return n && !subtaskAllTagOptions.value.includes(n)
})

function subtaskAddNewTag() {
  const t = normalizeTag(subtaskTagSearch.value)
  if (!t || !openSubtask.value) return
  const tags = [...(openSubtask.value.tags ?? [])]
  if (!tags.includes(t)) tags.push(t)
  emit('subtaskUpdate', openSubtask.value.id, { tags })
  subtaskTagSearch.value = ''
}
function subtaskToggleTag(tag) {
  if (!openSubtask.value) return
  const tags = [...(openSubtask.value.tags ?? [])]
  const idx  = tags.indexOf(tag)
  if (idx === -1) tags.push(tag); else tags.splice(idx, 1)
  emit('subtaskUpdate', openSubtask.value.id, { tags })
}
function onSubtaskDescBlur(e) {
  if (!openSubtask.value) return
  const val = e.target.innerText
  if (val !== (openSubtask.value.description ?? '')) {
    emit('subtaskUpdate', openSubtask.value.id, { description: val })
  }
}

const completedSubtasksCount = computed(() => props.task.subtasks?.filter(s => s.completed).length ?? 0)
const subtaskProgress = computed(() => {
  const total = props.task.subtasks?.length ?? 0
  return total === 0 ? 0 : Math.round((completedSubtasksCount.value / total) * 100)
})

const currentColumn = computed(() => props.columns.find(c => c.id === props.task.board_column_id))

const completedAgo = computed(() => {
  if (!props.task.completed_at) return ''
  return new Date(props.task.completed_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
})

const dateRangeLabel = computed(() => {
  const s = props.task.start_date, d = props.task.due_date
  if (s && d && s !== d) return `${formatDate(s)} → ${formatDate(d)}`
  return formatDate(d || s)
})

function dateValue(d) {
  if (!d) return ''
  return d.toString().slice(0, 10)
}
function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
function formatAgo(date) {
  if (!date) return ''
  const d = new Date(date)
  const diff = (Date.now() - d) / 1000
  if (diff < 60)    return 'just now'
  if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}
function renderCommentBody(body) {
  if (!body) return ''
  const out = []
  const re = /@\[([^\]]+)\]\(user:(\d+)\)/g
  let last = 0, m
  while ((m = re.exec(body)) !== null) {
    out.push(escapeHtml(body.slice(last, m.index)))
    out.push(`<span class="mention">@${escapeHtml(m[1])}</span>`)
    last = m.index + m[0].length
  }
  out.push(escapeHtml(body.slice(last)))
  return out.join('').replace(/\n/g, '<br>')
}

function escapeHtml(s) {
  if (s === null || s === undefined) return ''
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}
function truncate(s, n = 80) {
  if (s === null || s === undefined) return ''
  s = String(s)
  return s.length > n ? s.slice(0, n - 1) + '…' : s
}
function pill(s) { return `<span class="activity-val">${escapeHtml(s)}</span>` }
function activityMessage(a) {
  const f = a.from_value ?? null
  const t = a.to_value ?? null
  const sub = a.subtask ? ` on subtask <span class="activity-val">${escapeHtml(a.subtask.title)}</span>` : ''
  switch (a.field) {
    case 'title':
      return `renamed task from ${pill(truncate(f?.value, 60))} to ${pill(truncate(t?.value, 60))}${sub}`
    case 'description':
      if (!f?.value) return `set description${sub}`
      if (!t?.value) return `cleared description${sub}`
      return `changed description${sub}`
    case 'priority':
      return `changed priority from ${pill(f?.value ?? '—')} to ${pill(t?.value ?? '—')}${sub}`
    case 'status':
      return t?.value ? `completed task${sub}` : `reopened task${sub}`
    case 'assignees': {
      const fromNames = (f?.names ?? []).join(', ') || 'no one'
      const toNames   = (t?.names ?? []).join(', ') || 'no one'
      return `changed assignees from ${pill(fromNames)} to ${pill(toNames)}${sub}`
    }
    case 'project':
      return `moved task from project ${pill(f?.name ?? '—')} to ${pill(t?.name ?? '—')}${sub}`
    case 'sprint': {
      const fromN = f?.name ?? 'Backlog'
      const toN   = t?.name ?? 'Backlog'
      return `moved task from ${pill(fromN)} to ${pill(toN)}${sub}`
    }
    case 'start_date':
      return `changed start date from ${pill(f?.value ?? '—')} to ${pill(t?.value ?? '—')}${sub}`
    case 'due_date':
      return `changed due date from ${pill(f?.value ?? '—')} to ${pill(t?.value ?? '—')}${sub}`
    case 'tags': {
      const fromT = (f?.value ?? []).join(', ') || '—'
      const toT   = (t?.value ?? []).join(', ') || '—'
      return `changed tags from ${pill(fromT)} to ${pill(toT)}${sub}`
    }
    default:
      return `updated ${escapeHtml(a.field)}${sub}`
  }
}

function onTitleBlur(e) {
  const val = e.target.innerText.trim()
  if (val && val !== props.task.title) emit('update', { title: val })
}
function onDescBlur(e) {
  const val = e.target.innerText
  if (val !== (props.task.description ?? '')) emit('update', { description: val })
}
function submitComment() {
  if (!newComment.value.trim()) return
  emit('comment', newComment.value.trim()); newComment.value = ''
}
function submitReply(parentId) {
  if (!replyText.value.trim()) return
  emit('reply', parentId, replyText.value.trim())
  replyText.value = ''; replyingTo.value = null
}
function openSubtaskInput() {
  showSubtaskInput.value = true
  nextTick(() => subtaskInputEl.value?.focus())
}
function submitSubtask() {
  const title = newSubtaskTitle.value.trim()
  if (!title) return
  // Second arg = parent id. Top-level subtasks hang off the root task.
  emit('subtask', { title }, props.task.id)
  newSubtaskTitle.value = ''
  showSubtaskInput.value = false
}
function cancelSubtask() {
  newSubtaskTitle.value = ''
  showSubtaskInput.value = false
}
function onSubtaskBlur() {
  if (newSubtaskTitle.value.trim()) submitSubtask()
  else cancelSubtask()
}

// ── Nested subtasks (subtasks of the open subtask) ──
function openNestedSubtaskInput() {
  showNestedSubtaskInput.value = true
  nextTick(() => nestedSubtaskInputEl.value?.focus())
}
function submitNestedSubtask() {
  const title = newNestedSubtaskTitle.value.trim()
  if (!title || !openSubtask.value) return
  // Parent is the currently open subtask — this is what creates a subtask
  // inside a subtask.
  emit('subtask', { title }, openSubtask.value.id)
  newNestedSubtaskTitle.value = ''
  showNestedSubtaskInput.value = false
}
function cancelNestedSubtask() {
  newNestedSubtaskTitle.value = ''
  showNestedSubtaskInput.value = false
}
function onNestedSubtaskBlur() {
  if (newNestedSubtaskTitle.value.trim()) submitNestedSubtask()
  else cancelNestedSubtask()
}
function copyId()   { copyText(props.task.key) }
function copyLink() { copyText(window.location.href) }
</script>

<style scoped>
/* ===== Side panel shell ===== */
.side-panel-backdrop {
  position: fixed; inset: 0;
  background: rgba(0, 0, 0, 0.15);
  z-index: 50;
  animation: tp-fadeIn 120ms ease-out;
}
:global([data-theme="dark"]) .side-panel-backdrop { background: rgba(0,0,0,0.4); }
.side-panel {
  position: fixed; top: 0; right: 0;
  height: 100vh;
  width: var(--panel-w, 480px); max-width: 92vw;
  background: var(--bg-panel);
  border-left: 1px solid var(--border);
  box-shadow: var(--shadow-lg);
  z-index: 51;
  display: flex; flex-direction: column;
  animation: tp-slideIn 180ms cubic-bezier(0.32, 0.72, 0, 1);
}
/* Full-width sheet on phones */
@media (max-width: 768px) {
  .side-panel { width: 100vw; max-width: 100vw; border-left: none; }
}
@keyframes tp-fadeIn  { from { opacity: 0 } to { opacity: 1 } }
@keyframes tp-slideIn { from { transform: translateX(40px); opacity: 0 } to { transform: none; opacity: 1 } }

/* ===== Header ===== */
.panel-header {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}
.panel-header > .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.panel-header .id    { font-family: var(--font-mono); font-size: 12px; color: var(--fg-muted); flex: 1; }

.lock-pill {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 12px; font-weight: 500;
  padding: 2px 8px; border-radius: 999px;
  background: color-mix(in oklab, var(--status-progress) 14%, var(--bg-panel));
  color: var(--status-progress);
  border: 1px solid color-mix(in oklab, var(--status-progress) 30%, var(--border));
}
.lock-pill :deep(svg) { width: 11px; height: 11px; }
.lock-pill.done {
  background: color-mix(in oklab, var(--status-done) 14%, var(--bg-panel));
  color: var(--status-done);
  border-color: color-mix(in oklab, var(--status-done) 30%, var(--border));
}

/* ===== Body ===== */
.panel-body {
  flex: 1; overflow-y: auto;
  padding: 20px 24px;
  display: flex; flex-direction: column;
  gap: 20px;
}

.panel-title {
  font-size: 20px; font-weight: 600; line-height: 1.3;
  color: var(--fg);
  border: 1px solid transparent;
  border-radius: 6px;
  padding: 6px 8px; margin: -6px -8px;
  cursor: text; word-break: break-word;
}
.panel-title:hover { background: var(--bg-hover); }
.panel-title:focus { outline: none; border-color: var(--accent); background: var(--bg-panel); }
.panel-title:empty::before { content: attr(data-placeholder); color: var(--fg-subtle); }

.panel-section { display: flex; flex-direction: column; gap: 8px; }
.panel-section-title {
  font-size: 12px; color: var(--fg-muted); font-weight: 500;
  text-transform: uppercase; letter-spacing: 0.04em; line-height: 1;
}
.section-head { display: flex; align-items: center; justify-content: space-between; }
.head-left    { display: flex; align-items: center; gap: 6px; }
.count-mono   { font-size: 11px; color: var(--fg-subtle); font-family: var(--font-mono); }

/* ===== Banners ===== */
.banner {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 16px;
  background: var(--accent-soft);
  border: 1px solid var(--border);
  color: var(--fg);
  font-size: 13px;
  border-radius: 6px;
}
.banner .text  { flex: 1; }
.banner-done   {
  background: color-mix(in oklab, var(--status-done) 10%, var(--bg-panel));
  border-color: color-mix(in oklab, var(--status-done) 25%, var(--border));
}
.banner-done .icon-done :deep(*) { stroke: var(--status-done); }
.banner-done .icon-done { color: var(--status-done); }

/* ===== Props grid ===== */
.props {
  display: grid;
  grid-template-columns: 100px 1fr;
  row-gap: 6px;
  align-items: center;
  font-size: 13px;
}
.props .key { color: var(--fg-muted); }
.props .val { color: var(--fg); min-width: 0; }

.prop-pill {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 2px 6px; border-radius: 4px;
  cursor: pointer;
  border: 1px solid transparent;
  margin: -2px -6px;
  font-size: 13px;
}
.prop-pill:hover { background: var(--bg-hover); border-color: var(--border); }
.prop-pill > .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.prop-pill .dim :deep(svg),
.prop-pill > .dim { width: 13px; height: 13px; color: var(--fg-muted); }
.sprint-val { display: inline-flex; align-items: center; gap: 6px; }
.sprint-val .dim :deep(svg) { width: 13px; height: 13px; color: var(--fg-muted); }

.muted { color: var(--fg-muted); }
.subtle { color: var(--fg-subtle); }
.small { font-size: 12px; }
.small-pad { font-size: 13px; padding: 8px 0; color: var(--fg-muted); }

.avatar-empty {
  width: 22px; height: 22px; border-radius: 50%;
  background: var(--bg-active); color: var(--fg-subtle);
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 11px;
}

.tags { display: flex; flex-wrap: wrap; gap: 4px; }
.tag {
  display: inline-flex; align-items: center;
  font-size: 11px;
  padding: 1px 6px;
  border-radius: 3px;
  background: var(--bg-sunken);
  color: var(--fg-muted);
  border: 1px solid var(--border);
}

/* Menus, items used inside <MenuItem> default slot */
.check-slot { width: 14px; display: inline-flex; align-items: center; }
.check      { width: 14px; height: 14px; color: var(--accent); }
.menu-label { font-size: 12px; color: var(--fg-subtle); padding: 6px 8px 2px; font-weight: 500; }
.menu-divider { height: 1px; background: var(--border); margin: 4px 0; }

.dropdown-pad { padding: 4px 8px 8px; }
.date-fields  { display: flex; flex-direction: column; gap: 8px; }
.field-block  { display: flex; flex-direction: column; gap: 4px; }
.field-label  { font-size: 11px; color: var(--fg-muted); }
.clear-btn    { align-self: flex-start; }

/* ===== Subtasks ===== */
.subtask-progress { height: 3px; background: var(--border); border-radius: 2px; overflow: hidden; }
.subtask-progress .bar { height: 100%; background: var(--status-done); border-radius: 2px; transition: width 220ms; }
.subtask-list { display: flex; flex-direction: column; }
.subtask-row {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 0;
  border-bottom: 1px solid var(--border);
}
.subtask-row:last-child { border-bottom: none; }
.subtask-check {
  width: 16px; height: 16px; border-radius: 4px;
  border: 1.5px solid var(--border-strong);
  background: transparent;
  flex-shrink: 0;
  display: grid; place-items: center;
  cursor: pointer; padding: 0;
  transition: background 100ms, border 100ms;
}
.subtask-check.done {
  background: var(--status-done);
  border-color: var(--status-done);
  color: #fff;
}
.subtask-check.empty { cursor: default; }
.subtask-check :deep(svg) { width: 10px; height: 10px; }
.subtask-check:disabled { cursor: default; }
.subtask-title {
  flex: 1; min-width: 0; font-size: 13px;
  color: var(--fg);
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.subtask-title.done { color: var(--fg-subtle); text-decoration: line-through; }
.subtask-key {
  font-family: var(--font-mono); font-size: 11px;
  color: var(--fg-subtle); flex-shrink: 0;
}
.subtask-add-row { display: flex; gap: 8px; margin-top: 8px; align-items: center; }
.subtask-add-row .input { height: 28px; font-size: 13px; padding: 0 8px; flex: 1; }

.subtask-title { cursor: pointer; }
.subtask-title:hover:not(.done) { color: var(--accent); }
.subtask-chips {
  display: inline-flex; align-items: center; gap: 5px; flex-shrink: 0;
}
.subtask-count {
  display: inline-flex; align-items: center; gap: 3px;
  font-size: 11px; font-family: var(--font-mono);
  color: var(--fg-subtle); white-space: nowrap; cursor: pointer;
}
.subtask-count:hover { color: var(--accent); }
.subtask-count svg { opacity: .7; }
.subtask-due {
  font-size: 11px; font-family: var(--font-mono);
  color: var(--fg-subtle); white-space: nowrap;
}
.subtask-due.urgent {
  color: var(--status-blocked); font-weight: 500;
}
.subtask-prio {
  width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0;
}
.subtask-remove {
  background: none; border: none; padding: 2px;
  display: inline-flex; align-items: center; justify-content: center;
  color: var(--fg-subtle); cursor: pointer; flex-shrink: 0;
}
.subtask-remove:hover { color: var(--fg); }
.subtask-remove :deep(svg) { width: 11px; height: 11px; }

/* Subtask detail layer (slides over) */
.side-panel.subtask-layer        { z-index: 300; }
.side-panel-backdrop.subtask-layer { z-index: 299; }

.crumb {
  display: inline-flex; align-items: center; gap: 5px;
  color: var(--fg-muted); font-size: 12px;
  max-width: 100%; min-width: 0;
}
.crumb-text {
  max-width: 220px; overflow: hidden;
  text-overflow: ellipsis; white-space: nowrap;
}
.spacer { flex: 1; }

.subtask-kicker {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 12px; color: var(--fg-subtle);
  margin-bottom: -8px;
}
.dim { color: var(--fg-muted); }
.dim :deep(svg) { width: 13px; height: 13px; color: var(--fg-muted); }

/* ===== Description ===== */
.description {
  font-size: 14px; line-height: 1.6;
  color: var(--fg);
  border: 1px solid transparent;
  border-radius: 6px;
  padding: 8px; margin: -8px;
  cursor: text;
  white-space: pre-wrap;
  min-height: 60px;
}
.description:hover { background: var(--bg-hover); }
.description:focus { outline: none; border-color: var(--accent); background: var(--bg-panel); }
.description.empty::before { content: "Add description…"; color: var(--fg-subtle); }
.description:focus.empty::before { content: ""; }

/* ===== Tabs ===== */
.minitabs {
  display: flex; gap: 12px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 12px;
}
.minitab {
  font-size: 13px; color: var(--fg-muted);
  padding: 8px 0;
  border: none; background: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  cursor: pointer; font-weight: 500;
  font-family: inherit;
}
.minitab:hover  { color: var(--fg); }
.minitab.active { color: var(--fg); border-bottom-color: var(--accent); }
.minitab .badge {
  font-size: 11px;
  background: var(--bg-active);
  color: var(--fg-muted);
  border-radius: 8px;
  padding: 0 5px; margin-left: 4px;
  font-variant-numeric: tabular-nums;
}

.vstack       { display: flex; flex-direction: column; gap: 4px; }
.vstack-tight { display: flex; flex-direction: column; }
.hstack-end   { display: flex; justify-content: flex-end; gap: 8px; margin-top: 6px; }

/* ===== Comments ===== */
.comment {
  display: flex; gap: 12px;
  padding: 8px 0;
}
.comment .body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.comment .author-row { display: flex; align-items: baseline; gap: 8px; margin-bottom: 2px; }
.comment .author { font-weight: 600; font-size: 13px; }
.comment .time   { font-size: 12px; color: var(--fg-subtle); }
.comment .text   { font-size: 14px; line-height: 1.5; color: var(--fg); white-space: pre-wrap; word-break: break-word; }
.comment .actions { display: flex; gap: 12px; margin-top: 4px; }
.comment .actions button {
  background: none; border: none; padding: 0; cursor: pointer;
  font-size: 12px; color: var(--fg-muted); font-weight: 500;
  font-family: inherit;
}
.comment .actions button:hover { color: var(--fg); }

.thread-replies {
  margin-left: 36px;
  padding-left: 12px;
  border-left: 2px solid var(--border);
  margin-top: 4px;
  display: flex; flex-direction: column;
}

.composer { display: flex; gap: 12px; margin-top: 12px; }
.composer .body { flex: 1; display: flex; flex-direction: column; }
.composer .send-row {
  display: flex; align-items: center; justify-content: space-between;
  margin-top: 8px;
}

/* ===== Audit ===== */
.audit-row {
  display: flex; gap: 12px;
  padding: 8px 0;
  font-size: 13px;
  align-items: flex-start;
}
.audit-row .dot-col {
  width: 24px; flex-shrink: 0;
  display: flex; flex-direction: column; align-items: center;
  position: relative;
}
.audit-row .dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--border-strong); margin-top: 7px;
}
.audit-row .line {
  position: absolute; top: 14px; bottom: -8px; left: 50%;
  width: 1px; background: var(--border);
  transform: translateX(-50%);
}
.audit-row:last-child .line { display: none; }
.audit-row .text { flex: 1; color: var(--fg-muted); }
.audit-row .text strong { color: var(--fg); font-weight: 600; }
.audit-row .time { font-size: 12px; color: var(--fg-subtle); white-space: nowrap; }
.comment .text .mention {
  display: inline-block;
  padding: 0 4px;
  background: rgba(99, 102, 241, 0.12);
  color: #4f46e5;
  border-radius: 4px;
  font-weight: 500;
}
.audit-row .activity-val {
  display: inline-block;
  padding: 0 6px;
  border-radius: 4px;
  background: var(--bg-subtle, rgba(0,0,0,0.05));
  color: var(--fg);
  font-weight: 500;
  font-size: 12.5px;
  max-width: 280px;
  overflow: hidden;
  text-overflow: ellipsis;
  vertical-align: baseline;
}

/* ===== Inputs ===== */
.input {
  display: flex; align-items: center;
  height: 32px;
  padding: 0 12px;
  border: 1px solid var(--border);
  background: var(--bg-panel);
  border-radius: 6px;
  font-size: 13px;
  width: 100%;
  color: var(--fg);
  font-family: inherit;
}
.input:focus  { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
.input::placeholder { color: var(--fg-subtle); }
.textarea     { height: auto; min-height: 64px; padding: 8px 12px; resize: vertical; line-height: 1.5; }
input[type="date"].input { padding: 0 8px; height: 28px; font-size: 13px; }

/* ===== Buttons ===== */
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 0 12px;
  height: 28px;
  border-radius: 6px;
  font-size: 13px; font-weight: 500;
  cursor: pointer;
  border: 1px solid transparent;
  background: none; color: inherit;
  font-family: inherit;
  white-space: nowrap;
  transition: background 80ms;
}
.btn:disabled       { opacity: 0.5; cursor: not-allowed; }
.btn.primary        { background: var(--accent); color: var(--accent-fg); border-color: var(--accent); }
.btn.primary:hover:not(:disabled) { background: var(--accent-hover); }
.btn.secondary      { background: var(--bg-panel); color: var(--fg); border-color: var(--border); }
.btn.secondary:hover:not(:disabled) { background: var(--bg-hover); }
.btn.ghost          { color: var(--fg-muted); }
.btn.ghost:hover:not(:disabled) { background: var(--bg-hover); color: var(--fg); }
.btn.sm             { height: 24px; padding: 0 8px; font-size: 12px; }
.btn.icon-only      { padding: 0; width: 28px; justify-content: center; }
.btn.icon-only.sm   { width: 24px; }
.btn :deep(svg)     { width: 14px; height: 14px; }

.kbd {
  display: inline-flex; align-items: center;
  font-family: var(--font-mono); font-size: 11px;
  padding: 1px 5px;
  border-radius: 3px;
  border: 1px solid var(--border);
  background: var(--bg-sunken);
  color: var(--fg-muted);
}

/* ===== Multi-assignee stack ===== */
.assignee-stack { display: inline-flex; align-items: center; }
.stack-item + .stack-item { margin-left: -6px; }
.stack-item :deep(div) { box-shadow: 0 0 0 2px var(--bg-panel); border-radius: 50%; }
.avatar-more {
  display: inline-flex; align-items: center; justify-content: center;
  width: 22px; height: 22px; border-radius: 50%;
  background: var(--bg-active); color: var(--fg-muted);
  font-size: 10px; font-weight: 600;
  box-shadow: 0 0 0 2px var(--bg-panel);
  margin-left: -6px;
}

/* ===== Participants ===== */
.participants-row { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
.participant-avatar-btn {
  background: none; border: none; padding: 0; cursor: pointer;
  display: inline-flex; border-radius: 50%;
}
.participant-avatar-btn:hover :deep(div) { outline: 2px solid var(--accent); outline-offset: 1px; }

/* Participants modal */
.tp-modal-backdrop {
  position: fixed; inset: 0; z-index: 200;
  background: rgba(0, 0, 0, 0.25);
}
.tp-modal {
  position: fixed; top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  z-index: 201;
  width: 360px; max-width: 92vw;
  background: var(--bg-panel);
  border: 1px solid var(--border);
  border-radius: 10px;
  box-shadow: var(--shadow-lg);
  display: flex; flex-direction: column;
  max-height: 80vh;
}
.tp-modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 16px 12px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}
.tp-modal-title { font-weight: 600; font-size: 15px; }
.tp-modal-body { overflow-y: auto; padding: 8px 16px 16px; }
.participant-row-item {
  display: flex; align-items: center; gap: 12px;
  padding: 8px 0;
  border-bottom: 1px solid var(--border);
}
.participant-row-item:last-child { border-bottom: none; }
.participant-meta { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.participant-name { font-size: 13px; font-weight: 500; }
.participant-roles { display: flex; gap: 4px; flex-wrap: wrap; }
.role-chip {
  font-size: 11px; padding: 1px 6px;
  border-radius: 3px; font-weight: 500;
  background: var(--bg-sunken); color: var(--fg-muted);
  border: 1px solid var(--border);
}
.role-chip.role-assignee {
  background: color-mix(in oklab, var(--accent) 12%, var(--bg-panel));
  color: var(--accent);
  border-color: color-mix(in oklab, var(--accent) 30%, var(--border));
}
.role-chip.role-commenter {
  background: color-mix(in oklab, var(--status-progress) 12%, var(--bg-panel));
  color: var(--status-progress);
  border-color: color-mix(in oklab, var(--status-progress) 30%, var(--border));
}
.role-chip.role-reporter {
  background: color-mix(in oklab, var(--status-done) 12%, var(--bg-panel));
  color: var(--status-done);
  border-color: color-mix(in oklab, var(--status-done) 30%, var(--border));
}
/* Granted task-level access (no project membership) — ties to the lock color. */
.role-chip.role-guest {
  background: color-mix(in oklab, var(--status-blocked) 12%, var(--bg-panel));
  color: var(--status-blocked);
  border-color: color-mix(in oklab, var(--status-blocked) 30%, var(--border));
}

/* ===== Access requests ===== */
.access-req-list { display: flex; flex-direction: column; gap: 8px; }
.access-req {
  display: flex; gap: 12px;
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: var(--r-md, 8px);
  background: var(--bg-panel);
  transition: opacity 200ms ease, background 200ms ease;
}
.access-req.is-approved { background: color-mix(in oklab, var(--status-done) 6%, var(--bg-panel)); }
.access-req.is-declined { opacity: 0.6; }
.access-req-body { flex: 1; min-width: 0; }
.access-req-top { display: flex; align-items: baseline; gap: 8px; }
.access-req-name { font-size: 13px; font-weight: 600; color: var(--fg); }
.access-req-time { font-size: 11px; color: var(--fg-subtle); }
.access-req-sub  { font-size: 12px; color: var(--fg-muted); margin-top: 1px; }
.access-req-msg  {
  font-size: 12px; line-height: 1.5; color: var(--fg);
  margin-top: 6px; padding: 8px 10px;
  background: var(--bg-sunken);
  border-radius: var(--r-sm, 6px);
}
.access-req-actions { display: flex; gap: 8px; margin-top: 10px; }
.access-req-actions :deep(svg) { width: 12px; height: 12px; }
.access-req-resolved {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 12px; font-weight: 500; margin-top: 10px;
}
.access-req-resolved :deep(svg) { width: 13px; height: 13px; }
.access-req-resolved.approved { color: var(--status-done); }
.access-req-resolved.declined { color: var(--fg-muted); }
</style>
