<template>
  <v-text-field
      :type="type === 'string'? 'text':type"
      outlined
      :value="value"
      :name="name"
      :label="label"
      :error="error != null"
      :error-messages="error"
      :step="step?step:null"
  ></v-text-field>
</template>

<script>
export default {
  name: 'StringInput',
  props: {
    type: {
      type: String,
      default: 'text'
    },
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
    }
  }
}
</script>

<style scoped>

</style>
