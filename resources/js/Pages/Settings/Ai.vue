<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { reactive, ref } from 'vue';

const props = defineProps({
    connection: {
        type: Object,
        required: true,
    },
});

const connection = ref({ ...props.connection });
const form = reactive({
    api_key: '',
    default_model: props.connection.default_model,
});
const errors = ref({});
const message = ref('');
const saving = ref(false);
const deleting = ref(false);
const verifying = ref(false);

const save = async () => {
    if (saving.value) {
        return;
    }

    saving.value = true;
    errors.value = {};
    message.value = '';

    try {
        const response = await axios.put(
            route('ai-settings.openai.update'),
            form,
        );

        connection.value = response.data.connection;
        form.api_key = '';
        form.default_model = connection.value.default_model;
        message.value = connection.value.verified_at
            ? 'OpenAI settings updated.'
            : 'OpenAI settings saved. Verify the connection before assigning it to agents.';
    } catch (error) {
        errors.value = error?.response?.data?.errors ?? {};
        message.value =
            error?.response?.data?.message ||
            'Unable to save OpenAI settings.';
    } finally {
        saving.value = false;
    }
};

const remove = async () => {
    if (
        deleting.value ||
        !window.confirm(
            'Delete this OpenAI connection? Assigned agents will no longer be able to run.',
        )
    ) {
        return;
    }

    deleting.value = true;
    errors.value = {};
    message.value = '';

    try {
        const response = await axios.delete(
            route('ai-settings.openai.destroy'),
        );

        connection.value = response.data.connection;
        form.api_key = '';
        form.default_model = connection.value.default_model;
        message.value = 'OpenAI connection deleted.';
    } catch (error) {
        message.value =
            error?.response?.data?.message ||
            'Unable to delete the OpenAI connection.';
    } finally {
        deleting.value = false;
    }
};

const verify = async () => {
    if (verifying.value || !connection.value.configured) {
        return;
    }

    verifying.value = true;
    errors.value = {};
    message.value = '';

    try {
        const response = await axios.post(
            route('ai-settings.openai.verify'),
        );

        connection.value = response.data.connection;
        message.value = 'OpenAI connection verified.';
    } catch (error) {
        message.value =
            error?.response?.data?.message ||
            'Unable to verify the OpenAI connection.';
    } finally {
        verifying.value = false;
    }
};
</script>

<template>
    <Head title="AI Settings" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    AI Settings
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Configure the provider used by your managed agents.
                </p>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <section class="rounded-lg bg-white p-6 shadow sm:p-8">
                    <div class="flex flex-col gap-3 border-b border-gray-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">
                                OpenAI
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                The API key is encrypted at rest and is never returned to the browser.
                            </p>
                        </div>
                        <span
                            class="w-fit rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                            :class="connection.verified_at
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : connection.configured
                                    ? 'border-amber-200 bg-amber-50 text-amber-700'
                                    : 'border-gray-200 bg-gray-50 text-gray-500'"
                        >
                            {{
                                connection.verified_at
                                    ? 'Verified'
                                    : connection.configured
                                        ? 'Not verified'
                                        : 'Not configured'
                            }}
                        </span>
                    </div>

                    <form class="mt-6 space-y-5" @submit.prevent="save">
                        <div>
                            <InputLabel for="openai-api-key" value="API Key" />
                            <TextInput
                                id="openai-api-key"
                                v-model="form.api_key"
                                type="password"
                                autocomplete="new-password"
                                class="mt-1 block w-full"
                                :placeholder="connection.configured
                                    ? 'Leave blank to keep the current key'
                                    : 'sk-...'"
                            />
                            <InputError
                                class="mt-2"
                                :message="errors.api_key?.[0]"
                            />
                        </div>

                        <div>
                            <InputLabel
                                for="openai-default-model"
                                value="Default Model"
                            />
                            <TextInput
                                id="openai-default-model"
                                v-model="form.default_model"
                                type="text"
                                class="mt-1 block w-full"
                                maxlength="120"
                                required
                            />
                            <p class="mt-2 text-xs text-gray-500">
                                Agents may override this model individually.
                            </p>
                            <InputError
                                class="mt-2"
                                :message="errors.default_model?.[0]"
                            />
                        </div>

                        <div
                            v-if="message"
                            class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700"
                        >
                            {{ message }}
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <SecondaryButton
                                    v-if="connection.configured"
                                    type="button"
                                    :disabled="verifying"
                                    @click="verify"
                                >
                                    {{ verifying ? 'Verifying...' : 'Verify Connection' }}
                                </SecondaryButton>
                                <SecondaryButton
                                    v-if="connection.configured"
                                    type="button"
                                    class="text-rose-700"
                                    :disabled="deleting"
                                    @click="remove"
                                >
                                    {{ deleting ? 'Deleting...' : 'Delete Connection' }}
                                </SecondaryButton>
                            </div>

                            <PrimaryButton
                                :disabled="saving"
                                :class="{ 'opacity-25': saving }"
                            >
                                {{ saving ? 'Saving...' : 'Save Settings' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
