<template>
    <Dialog
        v-model:visible="localVisible"
    >

    </Dialog>
</template>

<script setup lang="ts">
import { Dialog } from 'primevue';
import { ref, watch } from 'vue';

const props = defineProps<{
    visible: boolean
}>()

const localVisible = ref(props.visible)

// Local mirror of the prop
const internalVisible = ref(props.visible)

const emit = defineEmits<{
  (e: 'update:visible', value: boolean): void
}>()

// Keep them in sync both ways
watch(() => props.visible, v => internalVisible.value = v)
watch(internalVisible, v => emit('update:visible', v))

</script>

<style scoped>

</style>
