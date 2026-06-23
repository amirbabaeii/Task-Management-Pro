import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import AgentRunsPanel from '@/Components/AgentRunsPanel.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

const task = {
    id: 42,
    assignees: [
        {
            id: 7,
            name: 'Noah Agent',
            is_agent: true,
            is_archived_agent: false,
        },
    ],
};

const flushPromises = async () => {
    await Promise.resolve();
    await Promise.resolve();
};

describe('AgentRunsPanel', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'route',
            vi.fn((name, params = {}) => `${name}:${JSON.stringify(params)}`),
        );
        axios.get.mockResolvedValue({ data: { agent_runs: [] } });
        axios.post.mockResolvedValue({
            data: {
                agent_run: {
                    id: 1,
                    status: 'queued',
                    autonomy: 'approval',
                    agent: { id: 7, name: 'Noah Agent' },
                    actions: [],
                    error: {},
                },
            },
        });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('starts a run for an assigned agent', async () => {
        const wrapper = mount(AgentRunsPanel, {
            props: {
                boardId: 3,
                task,
            },
            global: {
                stubs: {
                    PrimaryButton: {
                        template: '<button v-bind="$attrs"><slot /></button>',
                    },
                    SecondaryButton: {
                        template: '<button v-bind="$attrs"><slot /></button>',
                    },
                },
            },
        });

        await flushPromises();

        await wrapper.get('button').trigger('click');

        expect(axios.post).toHaveBeenCalledWith(
            'tasks.agent-runs.store:{"board":3,"task":42}',
            { agent_id: 7 },
        );
        expect(wrapper.text()).toContain('Queued');
    });

    it('renders proposed actions and emits refresh after approval', async () => {
        axios.get.mockResolvedValue({
            data: {
                agent_runs: [
                    {
                        id: 9,
                        status: 'awaiting_approval',
                        autonomy: 'approval',
                        summary: 'Please review.',
                        agent: { id: 7, name: 'Noah Agent' },
                        error: {},
                        actions: [
                            {
                                id: 15,
                                type: 'update_task_fields',
                                status: 'proposed',
                                payload: {
                                    fields: {
                                        title: 'Better title',
                                    },
                                },
                            },
                        ],
                    },
                ],
            },
        });
        axios.post.mockResolvedValue({
            data: {
                agent_run: {
                    id: 9,
                    status: 'completed',
                    autonomy: 'approval',
                    agent: { id: 7, name: 'Noah Agent' },
                    error: {},
                    actions: [
                        {
                            id: 15,
                            type: 'update_task_fields',
                            status: 'applied',
                            payload: {
                                fields: {
                                    title: 'Better title',
                                },
                            },
                        },
                    ],
                },
            },
        });

        const wrapper = mount(AgentRunsPanel, {
            props: {
                boardId: 3,
                task,
            },
            global: {
                stubs: {
                    PrimaryButton: {
                        template: '<button v-bind="$attrs"><slot /></button>',
                    },
                    SecondaryButton: {
                        template: '<button v-bind="$attrs"><slot /></button>',
                    },
                },
            },
        });

        await flushPromises();

        expect(wrapper.text()).toContain('Needs approval');
        expect(wrapper.text()).toContain('title: Better title');

        const approveButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Approve');

        await approveButton.trigger('click');

        expect(axios.post).toHaveBeenCalledWith(
            'agent-runs.actions.approve:{"agentRun":9,"action":15}',
        );
        expect(wrapper.emitted('task-refresh-needed')).toBeTruthy();
    });

    it('hides approve all when no proposed actions remain', async () => {
        axios.get.mockResolvedValue({
            data: {
                agent_runs: [
                    {
                        id: 10,
                        status: 'awaiting_approval',
                        autonomy: 'approval',
                        summary: 'History only.',
                        agent: { id: 7, name: 'Noah Agent' },
                        error: {},
                        actions: [
                            {
                                id: 16,
                                type: 'add_comment',
                                status: 'rejected',
                                payload: {
                                    comment: 'Skip this recommendation.',
                                },
                            },
                        ],
                    },
                ],
            },
        });

        const wrapper = mount(AgentRunsPanel, {
            props: {
                boardId: 3,
                task,
            },
            global: {
                stubs: {
                    PrimaryButton: {
                        template: '<button v-bind="$attrs"><slot /></button>',
                    },
                    SecondaryButton: {
                        template: '<button v-bind="$attrs"><slot /></button>',
                    },
                },
            },
        });

        await flushPromises();

        expect(wrapper.text()).toContain('Rejected');
        expect(wrapper.text()).not.toContain('Approve All');
    });
});
