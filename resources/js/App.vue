<template>
  <v-app>
    <left-bar/>
    <app-header/>
    <v-main>
      <CRUDLayout>
        <span slot="title">test</span>
        <v-btn slot="title" color="primary"><v-icon>mdi-plus</v-icon>Create</v-btn>
        <TableComponent :headers="[{text:'test', value:'value'}]"></TableComponent>
      </CRUDLayout>
      <app-footer/>
    </v-main>
  </v-app>
</template>

<script>

export default {
  name: 'App',
}
</script>
