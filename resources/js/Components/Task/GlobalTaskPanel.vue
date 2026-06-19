<!--
  GlobalTaskPanel — opens the rich TaskPanel as a right-side overlay anywhere
  in the app (Dashboard, My tasks, Inbox) WITHOUT navigating to the board.

  Given a `taskId`, it fetches the full task + reference data from
  GET /tasks/{task}/details (same shape Projects/Show passes to <TaskPanel>),
  then wires every mutation to the same routes the board uses. Because the
  host page (e.g. Dashboard) doesn't carry the panel's task in its props, each
  mutation re-fetches the task detail so the panel stays in sync.
-->
<template>
  <TaskPanel
    v-if="task"
    :task="task"
    :columns="columns"
    :allUsers="allUsers"
    :allProjects="allProjects"
    :allSprints="sprints"
    :allTags="allTags"
    :project="project"
    :locked="locked"
    @close="close"
    @update="handleUpdate"
    @complete="complete"
    @uncomplete="uncomplete"
    @delete="remove"
    @comment="postComment"
    @reply="postReply"
    @subtask="addSubtask"
    @subtaskToggle="toggleSubtask"
    @subtaskRemove="removeSubtask"
    @subtaskUpdate="handleSubtaskUpdate"
    @subtaskComment="postSubtaskComment"
    @subtaskReply="postSubtaskReply"
    @attachmentUpload="uploadAttachment"
    @attachmentRemove="removeAttachment"
  />

  <NoAccessPanel
    v-else-if="noAccess"
    :task="noAccess.task"
    :project="noAccess.project"
    :approvers="noAccess.approvers"
    :pending-request="noAccess.pendingRequest"
    @close="close"
  />
</template>

<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import TaskPanel from '@/Components/Task/TaskPanel.vue'
import NoAccessPanel from '@/Components/Task/NoAccessPanel.vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  taskId: { type: [Number, String], default: null },
})
const emit = defineEmits(['close'])
const { toast } = useToast()

const task        = ref(null)
const columns     = ref([])
const allUsers    = ref([])
const allProjects = ref([])
const sprints     = ref([])
const allTags     = ref([])
const project     = ref(null)
const locked      = ref(false)
const noAccess    = ref(null)

let loadToken = 0

async function load(id) {
  if (!id) { task.value = null; noAccess.value = null; return }
  const myToken = ++loadToken
  try {
    const { data } = await axios.get(route('tasks.details', id))
    if (myToken !== loadToken) return // a newer open superseded this one
    noAccess.value    = null
    task.value        = data.task
    columns.value     = data.columns
    allUsers.value    = data.allUsers
    allProjects.value = data.allProjects
    sprints.value     = data.sprints
    allTags.value     = data.allTags ?? []
    project.value     = data.project
    locked.value      = data.locked
  } catch (e) {
    if (myToken !== loadToken) return
    // A 403 with hasAccess:false is expected — show the locked preview.
    if (e.response?.status === 403 && e.response.data?.hasAccess === false) {
      task.value = null
      noAccess.value = e.response.data
    } else {
      task.value = null
      noAccess.value = null
      emit('close')
    }
  }
}

function refetch() {
  if (task.value) load(task.value.uuid)
}

// Routes expose uuids; subtasks emit their integer id, so map it to the uuid.
// Subtasks nest to any depth, so search the whole tree, not just direct children.
function findSubtaskNode(id, node = task.value) {
  for (const s of node?.subtasks ?? []) {
    if (s.id === id) return s
    const deep = findSubtaskNode(id, s)
    if (deep) return deep
  }
  return null
}
function findSubtaskParentNode(id, node = task.value) {
  for (const s of node?.subtasks ?? []) {
    if (s.id === id) return node
    const deep = findSubtaskParentNode(id, s)
    if (deep) return deep
  }
  return null
}
const subtaskUuid       = (id) => findSubtaskNode(id)?.uuid ?? id
const subtaskParentUuid = (id) => findSubtaskParentNode(id)?.uuid ?? task.value?.uuid

watch(() => props.taskId, (id) => load(id), { immediate: true })

function close() {
  loadToken++
  task.value = null
  noAccess.value = null
  emit('close')
}

// ── Mutations — same routes the board uses; re-fetch the panel after each
// so edits made off-board are reflected immediately. preserveState keeps this
// component mounted across the Inertia round-trip.
const opts = (after) => ({
  preserveScroll: true,
  preserveState: true,
  onSuccess: after,
})

function handleUpdate(data) {
  router.patch(route('tasks.update', task.value.uuid), data, opts(refetch))
}
function complete() {
  router.post(route('tasks.complete', task.value.uuid), {}, opts(() => { toast('Task marked complete'); refetch() }))
}
function uncomplete() {
  router.post(route('tasks.uncomplete', task.value.uuid), {}, opts(() => { toast('Task reopened'); refetch() }))
}
function remove() {
  const uuid = task.value.uuid
  close()
  router.delete(route('tasks.destroy', uuid), { preserveScroll: true, preserveState: true, onSuccess: () => toast('Task deleted') })
}
function postComment(body) {
  router.post(route('tasks.comments.store', task.value.uuid), { body }, opts(refetch))
}
function postReply(commentId, body) {
  router.post(route('tasks.comments.reply', [task.value.uuid, commentId]), { body }, opts(refetch))
}
function postSubtaskComment(subtaskId, body) {
  router.post(route('tasks.comments.store', subtaskUuid(subtaskId)), { body }, opts(refetch))
}
function postSubtaskReply(subtaskId, commentId, body) {
  router.post(route('tasks.comments.reply', [subtaskUuid(subtaskId), commentId]), { body }, opts(refetch))
}
function addSubtask(data, parentId) {
  // parentId is the immediate parent: the root task, or a subtask for nesting.
  const parentUuid = (parentId == null || parentId === task.value?.id)
    ? task.value.uuid
    : subtaskUuid(parentId)
  router.post(route('tasks.subtasks.store', parentUuid), data, opts(refetch))
}
function toggleSubtask(subtaskId, completed) {
  const routeName = completed ? 'tasks.complete' : 'tasks.uncomplete'
  router.post(route(routeName, subtaskUuid(subtaskId)), {}, opts(refetch))
}
function removeSubtask(subtaskId) {
  router.delete(route('tasks.destroy', subtaskUuid(subtaskId)), opts(refetch))
}
function handleSubtaskUpdate(subtaskId, data) {
  router.patch(route('tasks.subtasks.update', [subtaskParentUuid(subtaskId), subtaskUuid(subtaskId)]), data, opts(refetch))
}
function uploadAttachment(file) {
  const form = new FormData()
  form.append('file', file)
  router.post(route('tasks.attachments.store', task.value.uuid), form, opts(refetch))
}
function removeAttachment(attachmentId) {
  router.delete(route('attachments.destroy', attachmentId), opts(refetch))
}
</script>
