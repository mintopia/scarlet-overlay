// @vitest-environment happy-dom
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

// uPlot needs a real canvas; mock it so the component's wiring (not canvas
// pixels) is exercised under jsdom.
const instances = [];
vi.mock('uplot', () => {
    const ctor = vi.fn(function (opts, data) {
        this.opts = opts; this.data = data;
        this.setData = vi.fn(); this.setSize = vi.fn(); this.destroy = vi.fn();
        instances.push(this);
    });
    return { default: ctor };
});

import uPlot from 'uplot';
import MiniChart from '../components/Admin/MiniChart.vue';

const data = [{ t: 1000, v: 1 }, { t: 1010, v: 2 }, { t: 1020, v: 3 }];

describe('MiniChart', () => {
    beforeEach(() => { instances.length = 0; uPlot.mockClear(); });

    it.each(['line', 'area', 'water', 'bipolar'])('mounts the %s variant and builds uPlot data', async (variant) => {
        const wrapper = mount(MiniChart, { props: { data, variant, stepSeconds: 10 } });
        await wrapper.vm.$nextTick();
        expect(uPlot).toHaveBeenCalledTimes(1);
        const built = instances[0].data;
        expect(built).toHaveLength(3);        // [xs, live, held]
        expect(built[1].slice(0, 3)).toEqual([1, 2, 3]);
        expect(wrapper.find('[role="img"]').exists()).toBe(true);
    });

    it('renders an aria summary with min/max/latest', () => {
        const wrapper = mount(MiniChart, { props: { data, stepSeconds: 10 } });
        expect(wrapper.find('[role="img"]').attributes('aria-label')).toMatch(/latest 3/i);
    });

    it('wires the tooltip via uPlot hooks.setCursor when showTooltip is on', async () => {
        const wrapper = mount(MiniChart, { props: { data, stepSeconds: 10, showTooltip: true } });
        await wrapper.vm.$nextTick();
        const opts = instances[0].opts;
        expect(Array.isArray(opts.hooks?.setCursor)).toBe(true);
        expect(typeof opts.hooks.setCursor[0]).toBe('function');
    });
});
