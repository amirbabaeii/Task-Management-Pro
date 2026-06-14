import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import BoardColumn from '@/Components/BoardColumn.vue';

const requiredProps = {
    status: 'pending',
    label: 'Pending',
    tasks: [],
    isTaskDragging: () => false,
    isTaskDropBefore: () => false,
    isTaskDropAfter: () => false,
};

describe('BoardColumn', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('selects the title once and preserves typing afterward', async () => {
        const select = vi
            .spyOn(HTMLInputElement.prototype, 'select')
            .mockImplementation(() => {});
        const wrapper = mount(BoardColumn, {
            props: {
                ...requiredProps,
                labelDraft: 'Pending',
                isEditingLabel: false,
                'onUpdate:labelDraft': (value) =>
                    wrapper.setProps({ labelDraft: value }),
            },
            global: {
                stubs: {
                    Dropdown: {
                        template: '<div><slot name="trigger" /><slot name="content" /></div>',
                    },
                    TaskCard: true,
                },
            },
        });

        await wrapper.setProps({ isEditingLabel: true });
        await nextTick();

        const input = wrapper.get('input');

        expect(select).toHaveBeenCalledTimes(1);

        await input.setValue('Planning');

        expect(input.element.value).toBe('Planning');
        expect(select).toHaveBeenCalledTimes(1);
    });
});
