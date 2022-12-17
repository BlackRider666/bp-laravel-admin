<template>
    <v-container fluid class="fill-height">
        <v-row class="justify-center">
            <v-col cols="6">
                <v-card>
                    <v-card-title class="justify-space-between align-content-space-between primary white--text">
                        <slot name="title"></slot>
                    </v-card-title>
                    <v-divider/>
                    <v-card-text>
                        <slot></slot>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<script>
export default {
    name: 'AuthLayout'
}
</script>

<style scoped>

</style>
