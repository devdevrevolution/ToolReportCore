<template>
    <label class="block text-xs text-gray-500">
        {{ label }}
        <select v-model="model" class="input mt-0.5">
            <option v-for="opt in options" :key="opt" :value="opt">
                {{ opt }}
            </option>
        </select>
    </label>
</template>

<script setup lang="ts">
const model = defineModel<string>({ required: true })

defineProps<{
    label: string
    options: string[]
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
