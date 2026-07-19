<template>
    <label class="block text-xs text-gray-500">
        {{ label }}
        <div class="mt-0.5 flex gap-1">
            <input
                type="color"
                v-model="model"
                class="h-8 w-8 cursor-pointer rounded border p-0.5"
            />
            <input
                type="text"
                v-model="model"
                class="input flex-1"
            />
        </div>
    </label>
</template>

<script setup lang="ts">
const model = defineModel<string>({ required: true })

defineProps<{
    label: string
}>()
</script>

<style scoped>
.input {
    display: block;
    width: 100%;
    border-radius: 0.25rem;
    border: 1px solid #d1d5db;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    line-height: 1rem;
}
.input:focus {
    border-color: #3b82f6;
    outline: 1px solid #3b82f6;
    outline-offset: -1px;
}
</style>