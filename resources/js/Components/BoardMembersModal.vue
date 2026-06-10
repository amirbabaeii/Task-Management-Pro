<script setup>
import { computed, ref, watch } from 'vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    members: {
        type: Array,
        default: () => [],
    },
    availableAgents: {
        type: Array,
        default: () => [],
    },
    isOwner: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    inviting: {
        type: Boolean,
        default: false,
    },
    removingUserId: {
        type: Number,
        default: null,
    },
    addingAgentId: {
        type: Number,
        default: null,
    },
    inviteError: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'invite', 'add-agent', 'remove']);

const inviteEmail = ref('');
const memberSearch = ref('');

const sortedMembers = computed(() =>
    [...props.members].sort((a, b) => {
        if (a.role === 'owner' && b.role !== 'owner') {
            return -1;
        }
        if (b.role === 'owner' && a.role !== 'owner') {
            return 1;
        }
        return a.name.localeCompare(b.name);
    }),
);

const filteredMembers = computed(() => {
    const query = memberSearch.value.trim().toLowerCase();

    if (!query) {
        return sortedMembers.value;
    }

    return sortedMembers.value.filter((member) =>
        [member.name, member.email]
            .filter(Boolean)
            .some((value) => value.toLowerCase().includes(query)),
    );
});

const sortedAvailableAgents = computed(() =>
    [...props.availableAgents].sort((a, b) => a.name.localeCompare(b.name)),
);

const memberStats = computed(() => [
    {
        label: 'Total',
        value: props.members.length,
    },
    {
        label: 'People',
        value: props.members.filter((member) => ! member.is_agent).length,
    },
    {
        label: 'Agents',
        value: props.members.filter((member) => member.is_agent).length,
    },
]);

const initials = (name = '') =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || '?';

const isArchivedAgent = (member) =>
    Boolean(
        member.is_archived_agent || (member.is_agent && member.agent_archived_at),
    );

const memberBadgeLabel = (member) => {
    if (isArchivedAgent(member)) {
        return 'Archived';
    }

    if (member.is_agent) {
        return 'Agent';
    }

    return member.role === 'owner' ? 'Owner' : 'Collaborator';
};

const memberBadgeClass = (member) => {
    if (isArchivedAgent(member)) {
        return 'bg-gray-100 text-gray-500';
    }

    if (member.is_agent) {
        return 'bg-teal-50 text-teal-700';
    }

    return member.role === 'owner'
        ? 'bg-amber-100 text-amber-700'
        : 'bg-gray-100 text-gray-600';
};

const submitInvite = () => {
    const email = inviteEmail.value.trim();
    if (! email) {
        return;
    }

    emit('invite', email);
};

watch(
    () => props.show,
    (next) => {
        if (next) {
            inviteEmail.value = '';
            memberSearch.value = '';
        }
    },
);

watch(
    () => props.inviting,
    (next, prev) => {
        // Clear input after a successful invite (transition false→true→false
        // with no inviteError).
        if (prev === true && next === false && ! props.inviteError) {
            inviteEmail.value = '';
        }
    },
);
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="p-6">
            <div class="flex flex-col gap-1 border-b border-gray-100 pb-4">
                <h3 class="text-lg font-semibold text-gray-900">Members</h3>
                <p class="text-sm text-gray-500">
                    {{
                        isOwner
                            ? 'Invite collaborators by email. They get full edit access on this board.'
                            : 'You are collaborating on this board.'
                    }}
                </p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <span
                        v-for="stat in memberStats"
                        :key="stat.label"
                        class="rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500"
                    >
                        {{ stat.label }} {{ stat.value }}
                    </span>
                </div>
            </div>

            <form
                v-if="isOwner"
                class="mt-5 flex items-start gap-3"
                @submit.prevent="submitInvite"
            >
                <div class="flex-1">
                    <InputLabel
                        for="invite-email"
                        value="Invite by email"
                        class="sr-only"
                    />
                    <TextInput
                        id="invite-email"
                        v-model="inviteEmail"
                        type="email"
                        class="block w-full"
                        placeholder="collaborator@example.com"
                        autocomplete="off"
                        :disabled="inviting"
                    />
                    <InputError class="mt-2" :message="inviteError" />
                </div>
                <PrimaryButton
                    :class="{ 'opacity-25': inviting || ! inviteEmail.trim() }"
                    :disabled="inviting || ! inviteEmail.trim()"
                >
                    {{ inviting ? 'Inviting...' : 'Invite' }}
                </PrimaryButton>
            </form>

            <div
                v-if="isOwner && sortedAvailableAgents.length"
                class="mt-5 rounded-md border border-gray-200 bg-gray-50"
            >
                <div class="flex items-center justify-between border-b border-gray-200 px-3 py-2">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Managed Agents
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ sortedAvailableAgents.length }} available
                    </div>
                </div>
                <ul class="divide-y divide-gray-200">
                    <li
                        v-for="agent in sortedAvailableAgents"
                        :key="agent.id"
                        class="flex items-center gap-3 px-3 py-2"
                    >
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-teal-100 text-[11px] font-semibold text-teal-700"
                        >
                            {{ initials(agent.name) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-gray-900">
                                {{ agent.name }}
                            </div>
                            <div class="truncate text-xs text-gray-500">
                                {{ agent.agent_title || 'AI agent' }} - {{ agent.email }}
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
                            :disabled="addingAgentId !== null"
                            @click="emit('add-agent', agent)"
                        >
                            {{ addingAgentId === agent.id ? 'Adding...' : 'Add' }}
                        </button>
                    </li>
                </ul>
            </div>

            <div class="mt-6">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Board Members
                    </h4>
                    <span class="text-xs text-gray-500">
                        {{ filteredMembers.length }} of {{ members.length }}
                    </span>
                </div>
                <TextInput
                    v-if="members.length"
                    v-model="memberSearch"
                    type="search"
                    class="mb-3 block w-full"
                    placeholder="Search members by name or email..."
                    autocomplete="off"
                />
                <div
                    v-if="loading && members.length === 0"
                    class="rounded-md border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500"
                >
                    Loading members...
                </div>
                <ul v-else-if="filteredMembers.length" class="divide-y divide-gray-100">
                    <li
                        v-for="member in filteredMembers"
                        :key="member.id"
                        class="flex items-center gap-3 py-3"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                            :class="
                                isArchivedAgent(member)
                                    ? 'bg-gray-100 text-gray-500'
                                    : 'bg-indigo-100 text-indigo-700'
                            "
                        >
                            {{ initials(member.name) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-gray-900">
                                {{ member.name }}
                            </div>
                            <div class="truncate text-xs text-gray-500">
                                {{ member.email }}
                            </div>
                        </div>
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                            :class="memberBadgeClass(member)"
                        >
                            {{ memberBadgeLabel(member) }}
                        </span>
                        <DangerButton
                            v-if="isOwner && member.role !== 'owner'"
                            type="button"
                            class="!px-2.5 !py-1 !text-[10px]"
                            :class="{ 'opacity-25': removingUserId === member.id }"
                            :disabled="removingUserId === member.id"
                            @click="emit('remove', member.id)"
                        >
                            {{ removingUserId === member.id ? 'Removing...' : 'Remove' }}
                        </DangerButton>
                    </li>
                </ul>
                <div
                    v-else
                    class="rounded-md border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500"
                >
                    No members match this search.
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end">
                <SecondaryButton @click="emit('close')">Close</SecondaryButton>
            </div>
        </div>
    </Modal>
</template>
