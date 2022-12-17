<template>
  <v-checkbox
    ref="checkbox"
    :input-value="Boolean(parseInt(value))"
    :value="true"
    :name="name"
    :label="label"
  ></v-checkbox>
</template>

<script>
export default {
  name: 'BooleanInput',
  props: {
    value: {
      type: String,
      default: 0
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
