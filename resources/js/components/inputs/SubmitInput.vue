<template>
<v-btn color="primary" type="submit">{{ label }}</v-btn>
</template>

<script>
export default {
  name: 'SubmitInput',
  props: {
    label: {
      type: String,
      default: ''
    }
  }
}
</script>

<style scoped>

</style>
