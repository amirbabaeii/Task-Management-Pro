<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TagInput from '@/Components/TagInput.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    isEditing: {
        type: Boolean,
        default: false,
    },
    saving: {
        type: Boolean,
        default: false,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    errorMessage: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'submit']);

const name = defineModel('name', { type: String, default: '' });
const email = defineModel('email', { type: String, default: '' });
const title = defineModel('title', { type: String, default: '' });
const profile = defineModel('profile', { type: String, default: '' });
const personality = defineModel('personality', { type: String, default: '' });
const skills = defineModel('skills', { type: Array, default: () => [] });
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <form class="p-6" @submit.prevent="emit('submit')">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ isEditing ? 'Edit Agent' : 'New Agent' }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Managed teammate profile
                    </p>
                </div>
                <SecondaryButton type="button" @click="emit('close')">
                    Close
                </SecondaryButton>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="agent-name" value="Name" />
                    <TextInput
                        id="agent-name"
                        v-model="name"
                        type="text"
                        class="mt-1 block w-full"
                        maxlength="120"
                        required
                    />
                    <InputError class="mt-2" :message="errors.name?.[0]" />
                </div>

                <div>
                    <InputLabel for="agent-email" value="Email" />
                    <TextInput
                        id="agent-email"
                        v-model="email"
                        type="email"
                        class="mt-1 block w-full"
                        maxlength="255"
                        required
                    />
                    <InputError class="mt-2" :message="errors.email?.[0]" />
                </div>

                <div class="sm:col-span-2">
                    <InputLabel for="agent-title" value="Profile Title" />
                    <TextInput
                        id="agent-title"
                        v-model="title"
                        type="text"
                        class="mt-1 block w-full"
                        maxlength="120"
                        placeholder="QA analyst, planner, researcher"
                    />
                    <InputError class="mt-2" :message="errors.agent_title?.[0]" />
                </div>

                <div>
                    <InputLabel for="agent-profile" value="Profile" />
                    <textarea
                        id="agent-profile"
                        v-model="profile"
                        rows="4"
                        maxlength="1000"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                    />
                    <InputError class="mt-2" :message="errors.agent_profile?.[0]" />
                </div>

                <div>
                    <InputLabel for="agent-personality" value="Personality" />
                    <textarea
                        id="agent-personality"
                        v-model="personality"
                        rows="4"
                        maxlength="1000"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                    />
                    <InputError class="mt-2" :message="errors.agent_personality?.[0]" />
                </div>

                <div class="sm:col-span-2">
                    <InputLabel for="agent-skills" value="Skills" />
                    <TagInput
                        id="agent-skills"
                        v-model="skills"
                        placeholder="Add a skill, e.g. testing"
                        :max-tags="12"
                        :max-tag-length="40"
                        :error="errors.agent_skills?.[0] || errors['agent_skills.0']?.[0]"
                    />
                </div>
            </div>

            <InputError class="mt-4" :message="errorMessage" />

            <div class="mt-6 flex items-center justify-end gap-3">
                <SecondaryButton type="button" @click="emit('close')">
                    Cancel
                </SecondaryButton>
                <PrimaryButton :class="{ 'opacity-25': saving }" :disabled="saving">
                    {{ saving ? 'Saving...' : (isEditing ? 'Save Agent' : 'Create Agent') }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
