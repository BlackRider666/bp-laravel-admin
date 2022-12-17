<template>
<v-container fluid class="fill-height align-content-start">
  <v-row>
    <v-col cols="12">
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
  name: 'CRUDLayout'
}
</script>

<style scoped>
.fill-height {
  height: calc(100% - 52px);
}
</style>
