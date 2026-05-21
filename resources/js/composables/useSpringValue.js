import { ref, computed, watch, onUnmounted } from 'vue';

const REDUCED_MOTION = typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export function useSpringValue(getter, options = {}) {
    const { tension = 120, friction = 14, precision = 0.01 } = options;

    const source = computed(getter);
    const animated = ref(source.value);

    if (REDUCED_MOTION) {
        watch(source, v => { animated.value = v; });
        return animated;
    }

    let velocity = 0;
    let rafId = null;
    let lastTime = null;
    let active = false;
    let initialized = source.value != null;

    function tick(time) {
        if (lastTime === null) lastTime = time;
        const dt = Math.min((time - lastTime) / 1000, 0.064);
        lastTime = time;

        const target = source.value ?? 0;
        const displacement = animated.value - target;
        const springForce = -tension * displacement;
        const dampingForce = -friction * velocity;
        velocity += (springForce + dampingForce) * dt;
        animated.value += velocity * dt;

        if (Math.abs(velocity) > precision || Math.abs(displacement) > precision) {
            rafId = requestAnimationFrame(tick);
        } else {
            animated.value = target;
            velocity = 0;
            active = false;
            lastTime = null;
        }
    }

    function start() {
        if (!active) {
            active = true;
            lastTime = null;
            rafId = requestAnimationFrame(tick);
        }
    }

    watch(source, (val) => {
        if (val == null) return;
        if (!initialized) {
            animated.value = val;
            initialized = true;
            return;
        }
        start();
    });

    onUnmounted(() => {
        if (rafId) cancelAnimationFrame(rafId);
    });

    return animated;
}

export function useAngleSpring(getter, options = {}) {
    const { tension = 80, friction = 12, precision = 0.5 } = options;

    const source = computed(getter);
    const animated = ref(source.value);

    if (REDUCED_MOTION) {
        watch(source, v => { animated.value = v; });
        return animated;
    }

    let velocity = 0;
    let rafId = null;
    let lastTime = null;
    let active = false;
    let initialized = source.value != null;

    function tick(time) {
        if (lastTime === null) lastTime = time;
        const dt = Math.min((time - lastTime) / 1000, 0.064);
        lastTime = time;

        const target = source.value ?? 0;
        let displacement = animated.value - target;
        while (displacement > 180) displacement -= 360;
        while (displacement < -180) displacement += 360;

        const springForce = -tension * displacement;
        const dampingForce = -friction * velocity;
        velocity += (springForce + dampingForce) * dt;
        animated.value += velocity * dt;

        if (Math.abs(velocity) > precision || Math.abs(displacement) > precision) {
            rafId = requestAnimationFrame(tick);
        } else {
            animated.value = target;
            velocity = 0;
            active = false;
            lastTime = null;
        }
    }

    function start() {
        if (!active) {
            active = true;
            lastTime = null;
            rafId = requestAnimationFrame(tick);
        }
    }

    watch(source, (val) => {
        if (val == null) return;
        if (!initialized) {
            animated.value = val;
            initialized = true;
            return;
        }
        start();
    });

    onUnmounted(() => {
        if (rafId) cancelAnimationFrame(rafId);
    });

    return animated;
}
