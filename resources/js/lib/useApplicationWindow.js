import { ref, onMounted, onUnmounted, watch } from "vue";
import { usePage } from "@inertiajs/vue3";

export function useApplicationWindow() {
    const page = usePage();
    let anchor = Date.parse(page.props.serverNow);
    let elapsedAtAnchor = performance.now();
    const currentTime = ref(anchor);
    let timer;

    watch(
        () => page.props.serverNow,
        (value) => {
            anchor = Date.parse(value);
            elapsedAtAnchor = performance.now();
            currentTime.value = anchor;
        },
    );
    onMounted(() => {
        timer = setInterval(() => {
            currentTime.value = anchor + performance.now() - elapsedAtAnchor;
        }, 250);
    });
    onUnmounted(() => clearInterval(timer));

    return (call) =>
        call.status === "PUBLICADA" &&
        currentTime.value >= Date.parse(call.opens_at) &&
        Math.floor(currentTime.value / 1000) <=
            Math.floor(Date.parse(call.closes_at) / 1000);
}
