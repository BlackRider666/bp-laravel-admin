<template>
    <v-checkbox
        ref="checkbox"
        :input-value="parseInt(value)"
        :false-value="0"
        :true-value="1"
        :name="name"
        :label="label"
        :value="1"
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
