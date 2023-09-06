<template>
    <div>
        <template v-for="lang in languages">
            <editor-input
                v-model="valueField[lang]"
                :name="name+'['+lang+']'"
                :label="label+' '+lang"
                :error="error != null"
                :error-messages="error"
            ></editor-input>
        </template>
    </div>
</template>

<script>
import EditorInput from "./EditorInput";

export default {
    name: "TranslatableEditorInput",
    components: {
        EditorInput,
    },
    props: {
        value: {
            type: Object,
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
        languages: {
            type: Array,
        }
    },
    data() {
        return {
            valueField: this.value,
        }
    },
}
</script>

<style scoped>

</style>
