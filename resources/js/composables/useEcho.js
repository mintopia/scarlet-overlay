import { ref, onMounted, onUnmounted } from 'vue';

export function useMetrics() {
    const metrics = ref(null);
    const lastUpdate = ref(null);
    let channel = null;

    onMounted(() => {
        if (!window.Echo) return;

        channel = window.Echo.channel('metrics');
        channel.listen('.metrics.updated', (data) => {
            metrics.value = data;
            lastUpdate.value = new Date();
        });
    });

    onUnmounted(() => {
        if (channel) {
            window.Echo.leave('metrics');
        }
    });

    return { metrics, lastUpdate };
}
