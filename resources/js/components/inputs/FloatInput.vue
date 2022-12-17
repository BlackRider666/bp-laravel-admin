<template>
<v-text-field
  type="number"
  step="0.01"
  :value="value"
  :name="name"
  :label="label"
></v-text-field>
</template>

<script>
export default {
  name: 'FloatInput',
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
