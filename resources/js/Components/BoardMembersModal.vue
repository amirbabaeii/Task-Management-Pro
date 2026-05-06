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
    inviteError: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'invite', 'remove']);

const inviteEmail = ref('');

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

const initials = (name = '') =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || '?';

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

            <div class="mt-6">
                <div
                    v-if="loading && members.length === 0"
                    class="rounded-md border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500"
                >
                    Loading members...
                </div>
                <ul v-else class="divide-y divide-gray-100">
                    <li
                        v-for="member in sortedMembers"
                        :key="member.id"
                        class="flex items-center gap-3 py-3"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700"
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
                            :class="
                                member.role === 'owner'
                                    ? 'bg-amber-100 text-amber-700'
                                    : 'bg-gray-100 text-gray-600'
                            "
                        >
                            {{ member.role === 'owner' ? 'Owner' : 'Collaborator' }}
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
            </div>

            <div class="mt-6 flex items-center justify-end">
                <SecondaryButton @click="emit('close')">Close</SecondaryButton>
            </div>
        </div>
    </Modal>
</template>
