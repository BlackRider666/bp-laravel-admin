<template>
<v-select
    :items="items"
    outlined
    :value="value"
    :name="name"
    :label="label"
    :error="error != null"
    :error-messages="error"
    :multiple="Boolean(parseInt(multiple))"
    chips
    deletable-chips
></v-select>
</template>

<script>
export default {
    name: 'SelectInput',
    props: {
        value: {
            type: Array,
        },
        name: {
            type: String,
            default: ''
        },
        label: {
            type: String,
            default: ''
        },
        error: {
            type: String,
            default: null
        },
        items: {
            type: Array,
            default: null,
        },
        multiple: {
            type:String,
            default:false
        }
    }
}
</script>

<style scoped>

</style>
