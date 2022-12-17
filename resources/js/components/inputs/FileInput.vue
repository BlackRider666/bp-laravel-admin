<template>
    <div>
        <template v-if="value">
            <v-card shaped class="pa-1" v-if="typefile.split('/')[0] === 'image'">
                <v-img
                    :src="value"
                    max-height="250"
                    contain
                ></v-img>
            </v-card>
            <a class="file-input__link" :href="value" target="_blank" v-else>
                <v-alert
                    :icon="false"
                    type="info">
                    {{typefile.split('/')[0] + ':' + typefile.split('/')[1]}}
                </v-alert>
            </a>
        </template>
        <v-file-input
            chips
            :outlined="!value"
            :prepend-icon="null"
            prepend-inner-icon="mdi-paperclip"
            :name="name"
            :label="label"
            :error="error != null"
            :error-messages="error"
        ></v-file-input>
    </div>
</template>

<script>
export default {
    name: "FileInput",
    props: {
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
        value: {
            type: String,
            default: null
        },
        typefile: {
            type: String,
            default: null,
        }
    }
}
</script>

<style scoped>
.file-input__link {
    text-decoration: none;
}
</style>
