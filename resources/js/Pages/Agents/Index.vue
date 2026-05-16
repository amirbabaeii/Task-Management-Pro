<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TagInput from '@/Components/TagInput.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    agents: {
        type: Array,
        default: () => [],
    },
});

const agents = ref([...props.agents]);
const editingAgentId = ref(null);
const saving = ref(false);
const deletingAgentId = ref(null);
const errors = ref({});
const errorMessage = ref('');

const blankForm = () => ({
    name: '',
    email: '',
    agent_title: '',
    agent_profile: '',
    agent_personality: '',
    agent_skills: [],
});

const form = reactive(blankForm());
const editingAgent = computed(
    () => agents.value.find((agent) => agent.id === editingAgentId.value) ?? null,
);
const isEditing = computed(() => editingAgent.value !== null);

const resetForm = () => {
    Object.assign(form, blankForm());
    editingAgentId.value = null;
    errors.value = {};
    errorMessage.value = '';
};

const editAgent = (agent) => {
    editingAgentId.value = agent.id;
    Object.assign(form, {
        name: agent.name ?? '',
        email: agent.email ?? '',
        agent_title: agent.title ?? '',
        agent_profile: agent.profile ?? '',
        agent_personality: agent.personality ?? '',
        agent_skills: Array.isArray(agent.skills) ? [...agent.skills] : [],
    });
    errors.value = {};
    errorMessage.value = '';
};

const upsertAgent = (agent) => {
    const index = agents.value.findIndex((item) => item.id === agent.id);

    if (index === -1) {
        agents.value = [...agents.value, agent].sort((a, b) =>
            a.name.localeCompare(b.name),
        );
        return;
    }

    agents.value = agents.value.map((item) =>
        item.id === agent.id ? agent : item,
    );
};

const submit = async () => {
    if (saving.value) {
        return;
    }

    saving.value = true;
    errors.value = {};
    errorMessage.value = '';

    try {
        const payload = {
            name: form.name,
            email: form.email,
            agent_title: form.agent_title,
            agent_profile: form.agent_profile,
            agent_personality: form.agent_personality,
            agent_skills: form.agent_skills,
        };
        const response = isEditing.value
            ? await axios.patch(
                route('agents.update', { agent: editingAgent.value.id }),
                payload,
            )
            : await axios.post(route('agents.store'), payload);

        if (response?.data?.agent) {
            upsertAgent(response.data.agent);
        }

        resetForm();
    } catch (error) {
        if (error?.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
            return;
        }

        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to save agent right now. Please try again.';
    } finally {
        saving.value = false;
    }
};

const deleteAgent = async (agent) => {
    if (deletingAgentId.value !== null) {
        return;
    }

    deletingAgentId.value = agent.id;
    errorMessage.value = '';

    try {
        const response = await axios.delete(
            route('agents.destroy', { agent: agent.id }),
        );
        const id = Number(response?.data?.id ?? agent.id);
        agents.value = agents.value.filter((item) => item.id !== id);

        if (editingAgentId.value === id) {
            resetForm();
        }
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to delete agent right now. Please try again.';
    } finally {
        deletingAgentId.value = null;
    }
};
</script>

<template>
    <Head title="Agents" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Agents
                </h2>
                <p class="text-sm text-gray-500">
                    Create AI teammates, define how they work, then invite them to boards like other users.
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.4fr)] lg:px-8">
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="mb-5 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                                {{ isEditing ? 'Edit Agent' : 'New Agent' }}
                            </h3>
                            <p class="mt-1 text-xs text-gray-500">
                                Agents are users managed by you.
                            </p>
                        </div>
                        <SecondaryButton v-if="isEditing" @click="resetForm">
                            Clear
                        </SecondaryButton>
                    </div>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div>
                            <InputLabel for="agent-name" value="Name" />
                            <TextInput
                                id="agent-name"
                                v-model="form.name"
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
                                v-model="form.email"
                                type="email"
                                class="mt-1 block w-full"
                                maxlength="255"
                                required
                            />
                            <InputError class="mt-2" :message="errors.email?.[0]" />
                        </div>

                        <div>
                            <InputLabel for="agent-title" value="Profile Title" />
                            <TextInput
                                id="agent-title"
                                v-model="form.agent_title"
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
                                v-model="form.agent_profile"
                                rows="3"
                                maxlength="1000"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                            />
                            <InputError class="mt-2" :message="errors.agent_profile?.[0]" />
                        </div>

                        <div>
                            <InputLabel for="agent-personality" value="Personality" />
                            <textarea
                                id="agent-personality"
                                v-model="form.agent_personality"
                                rows="3"
                                maxlength="1000"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                            />
                            <InputError class="mt-2" :message="errors.agent_personality?.[0]" />
                        </div>

                        <div>
                            <InputLabel for="agent-skills" value="Skills" />
                            <TagInput
                                id="agent-skills"
                                v-model="form.agent_skills"
                                placeholder="Add a skill, e.g. testing"
                                :max-tags="12"
                                :max-tag-length="40"
                                :error="errors.agent_skills?.[0] || errors['agent_skills.0']?.[0]"
                            />
                        </div>

                        <InputError :message="errorMessage" />

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <SecondaryButton type="button" @click="resetForm">
                                Cancel
                            </SecondaryButton>
                            <PrimaryButton
                                :class="{ 'opacity-25': saving }"
                                :disabled="saving"
                            >
                                {{ saving ? 'Saving...' : (isEditing ? 'Save Agent' : 'Create Agent') }}
                            </PrimaryButton>
                        </div>
                    </form>
                </section>

                <section>
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                            Managed Agents
                        </h3>
                        <span class="text-xs text-gray-500">
                            {{ agents.length }} total
                        </span>
                    </div>

                    <div
                        v-if="agents.length"
                        class="grid gap-3 md:grid-cols-2"
                    >
                        <article
                            v-for="agent in agents"
                            :key="agent.id"
                            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-gray-900">
                                        {{ agent.name }}
                                    </div>
                                    <div class="mt-1 truncate text-xs text-gray-500">
                                        {{ agent.title || 'AI agent' }} · {{ agent.email }}
                                    </div>
                                </div>
                                <span class="rounded-full bg-teal-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-teal-700">
                                    Agent
                                </span>
                            </div>

                            <p class="mt-4 text-sm leading-6 text-gray-600">
                                {{ agent.profile || 'No profile yet.' }}
                            </p>

                            <div
                                v-if="agent.skills?.length"
                                class="mt-4 flex flex-wrap gap-2"
                            >
                                <span
                                    v-for="skill in agent.skills"
                                    :key="`${agent.id}-${skill}`"
                                    class="rounded-full border border-gray-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-600"
                                >
                                    {{ skill }}
                                </span>
                            </div>

                            <div class="mt-4 flex items-center justify-end gap-2">
                                <SecondaryButton @click="editAgent(agent)">
                                    Edit
                                </SecondaryButton>
                                <button
                                    type="button"
                                    class="rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-widest text-rose-600 transition hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 disabled:opacity-40"
                                    :disabled="deletingAgentId === agent.id"
                                    @click="deleteAgent(agent)"
                                >
                                    {{ deletingAgentId === agent.id ? 'Deleting...' : 'Delete' }}
                                </button>
                            </div>
                        </article>
                    </div>

                    <div
                        v-else
                        class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500"
                    >
                        No agents yet.
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
