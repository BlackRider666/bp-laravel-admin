<template>
<v-text-field
  type="numeric"
  :value="value"
  :name="name"
  :label="label"
></v-text-field>
</template>

<script>
export default {
  name: 'IntegerInput',
  props: {
    value: {
      type: Boolean,
      default: false
    },
    name: {
      type: String,
      default: ''
    },
    label: {
      type: String,
      default: ''
    }
  }
}
</script>

<style scoped>

</style>
