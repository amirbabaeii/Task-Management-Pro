<script setup>
import { computed, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import NavLink from '@/Components/NavLink.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const showingBoardModal = ref(false);
const page = usePage();
const boardForm = useForm({
    name: '',
    description: '',
});

const boards = computed(() => page.props.boards ?? []);
const currentBoard = computed(() => page.props.currentBoard ?? null);
const currentBoardName = computed(() => currentBoard.value?.name ?? 'Task Board');
const currentBoardHref = computed(() =>
    currentBoard.value
        ? route('tasks.board', { board: currentBoard.value.id })
        : route('tasks.board'),
);
const boardMenuActive = computed(() => route().current('tasks.board'));

const closeBoardModal = () => {
    showingBoardModal.value = false;
    boardForm.reset();
    boardForm.clearErrors();
};

const submitBoard = () => {
    boardForm.post(route('boards.store'), {
        preserveScroll: true,
        onSuccess: () => closeBoardModal(),
    });
};
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav
                class="border-b border-gray-100 bg-white"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-gray-800"
                                    />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"
                            >
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Dashboard
                                </NavLink>
                                <NavLink
                                    :href="route('agents.index')"
                                    :active="route().current('agents.index')"
                                >
                                    Agents
                                </NavLink>
                                <Dropdown align="left" width="64" content-classes="bg-white">
                                    <template #trigger>
                                        <button
                                            type="button"
                                            class="inline-flex h-full items-center gap-2 border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none"
                                            :class="boardMenuActive
                                                ? 'border-indigo-400 text-gray-900 focus:border-indigo-700'
                                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 focus:border-gray-300 focus:text-gray-700'"
                                        >
                                            <span class="max-w-[11rem] truncate">
                                                {{ currentBoardName }}
                                            </span>
                                            <svg
                                                class="h-4 w-4"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 011.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </button>
                                    </template>

                                    <template #content>
                                        <div class="flex max-h-80 flex-col">
                                            <div class="overflow-y-auto py-1">
                                                <Link
                                                    v-for="board in boards"
                                                    :key="board.id"
                                                    :href="route('tasks.board', { board: board.id })"
                                                    class="block px-4 py-2 text-sm leading-5 transition duration-150 ease-in-out focus:outline-none"
                                                    :class="Number(board.id) === Number(currentBoard?.id)
                                                        ? 'bg-indigo-50 font-medium text-indigo-700'
                                                        : 'text-gray-700 hover:bg-gray-100 focus:bg-gray-100'"
                                                >
                                                    {{ board.name }}
                                                </Link>
                                            </div>
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-2 border-t border-gray-100 bg-gray-50 px-4 py-2.5 text-sm font-medium text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none"
                                                @click="showingBoardModal = true"
                                            >
                                                <svg
                                                    class="h-4 w-4 shrink-0"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M10 4.25a.75.75 0 01.75.75v4.25H15a.75.75 0 010 1.5h-4.25V15a.75.75 0 01-1.5 0v-4.25H5a.75.75 0 010-1.5h4.25V5a.75.75 0 01.75-.75z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                                <span>Create board</span>
                                            </button>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <NotificationBell class="me-2" />
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('ai-settings.edit')"
                                        >
                                            AI Settings
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('agents.index')"
                            :active="route().current('agents.index')"
                        >
                            Agents
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="currentBoardHref"
                            :active="route().current('tasks.board')"
                        >
                            {{ currentBoardName }}
                        </ResponsiveNavLink>
                        <div
                            v-if="boards.length"
                            class="space-y-1 border-t border-gray-100 pb-2 pt-2"
                        >
                            <Link
                                v-for="board in boards"
                                :key="board.id"
                                :href="route('tasks.board', { board: board.id })"
                                class="block px-6 py-2 text-sm transition"
                                :class="Number(board.id) === Number(currentBoard?.id)
                                    ? 'bg-indigo-50 text-indigo-700'
                                    : 'text-gray-500 hover:bg-gray-50 hover:text-gray-700'"
                            >
                                {{ board.name }}
                            </Link>
                            <button
                                type="button"
                                class="block w-full px-6 py-2 text-left text-sm text-gray-500 transition hover:bg-gray-50 hover:text-gray-700"
                                @click="showingBoardModal = true"
                            >
                                + New Board
                            </button>
                        </div>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-gray-200 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('ai-settings.edit')">
                                AI Settings
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="bg-white shadow"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>

        <Modal
            :show="showingBoardModal"
            max-width="lg"
            @close="closeBoardModal"
        >
            <div class="p-6">
                <div class="flex flex-col gap-1 border-b border-gray-100 pb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Create a new board
                    </h3>
                    <p class="text-sm text-gray-500">
                        New boards start with the default workflow columns.
                    </p>
                </div>

                <form
                    class="mt-6 space-y-4"
                    @submit.prevent="submitBoard"
                >
                    <div>
                        <InputLabel
                            for="layout-board-name"
                            value="Board Name"
                        />
                        <TextInput
                            id="layout-board-name"
                            v-model="boardForm.name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            maxlength="100"
                            autocomplete="off"
                        />
                        <InputError
                            class="mt-2"
                            :message="boardForm.errors.name"
                        />
                    </div>

                    <div>
                        <InputLabel
                            for="layout-board-description"
                            value="Description"
                        />
                        <textarea
                            id="layout-board-description"
                            v-model="boardForm.description"
                            rows="3"
                            maxlength="280"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Describe what this board is for."
                        />
                        <InputError
                            class="mt-2"
                            :message="boardForm.errors.description"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <SecondaryButton @click="closeBoardModal">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            :class="{
                                'opacity-25': boardForm.processing,
                            }"
                            :disabled="boardForm.processing"
                        >
                            {{
                                boardForm.processing
                                    ? 'Creating...'
                                    : 'Create Board'
                            }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>
