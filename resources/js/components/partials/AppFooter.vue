<template>
  <v-footer>
    <p>{{ copyright }}</p>
  </v-footer>
</template>

<script>
export default {
    name: 'AppFooter',
    props: {
        copyright: {
            type: String,
            default: ''
        }
    }
}
</script>

<style scoped>

</style>
