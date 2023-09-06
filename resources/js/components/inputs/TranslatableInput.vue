<template>
    <template>
        <div>
            <template v-for="lang in languages">
                <v-text-field
                    outlined
                    :value="value[lang]"
                    :name="name+'['+lang+']'"
                    :label="label+' '+lang"
                    :error="error != null"
                    :error-messages="error"
                    :step="step?step:null"
                ></v-text-field>
            </template>
        </div>
</template>

<script>
export default {
    name: "TranslatableInput",
    props: {
        value: {
            type: String,
            default: false
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
        step:{
            default:null,
        },
        languages: {
            type: Array,
        }
    },
}
</script>

<style scoped>

</style>
