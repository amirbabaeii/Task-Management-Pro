<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    form: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close', 'submit']);
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="p-6">
            <div class="flex flex-col gap-1 border-b border-gray-100 pb-4">
                <h3 class="text-lg font-semibold text-gray-900">
                    Add a board column
                </h3>
                <p class="text-sm text-gray-500">
                    Create a new status column for this board.
                </p>
            </div>

            <form
                class="mt-6 space-y-4"
                @submit.prevent="emit('submit')"
            >
                <div>
                    <InputLabel for="column-label" value="Column Title" />
                    <TextInput
                        id="column-label"
                        v-model="form.label"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        maxlength="40"
                        autocomplete="off"
                    />
                    <InputError class="mt-2" :message="form.errors.label" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <SecondaryButton @click="emit('close')">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Adding...' : 'Add Column' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
