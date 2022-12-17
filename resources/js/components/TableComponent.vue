<template>
  <v-data-table
    :headers="headers"
    :items="items.data"
    :options.sync="pagination"
    @update:options="searchItems"
    :footer-props="{
                  itemsPerPageOptions:[5,10,15,20]
              }"
    class="elevation-1"
    :server-items-length="items.total"
  >
      <template v-slot:item.actions="{ item }">
          <v-btn
              icon
              text
              :href="routes.show.replace(':id', item.id)"
              color="info"
          ><v-icon small>mdi-eye</v-icon></v-btn>
          <v-btn
              icon
              text
              :href="routes.edit.replace(':id', item.id)"
              color="warning"
          ><v-icon small>mdi-pencil</v-icon></v-btn>
          <v-btn
              icon
              text
              color="error"
          ><v-icon small @click="deleteItem(item.id)">mdi-delete</v-icon></v-btn>
          <form :ref="`delete-form-${item.id}`" :action="routes.delete.replace(':id', item.id)" method="POST" style="display: none;">
              <input type="hidden" name="_token" :value="csrftoken"/>
              <input type="hidden" name="_method" value="DELETE"/>
          </form>
      </template>
      <template v-if="Boolean(searchable)" v-slot:top>
          <v-toolbar flat>
              <form ref="search-form" class="flex-fill" :action="routes.index" method="GET">
                  <input type="hidden" name="sortBy" v-if="pagination.sortBy.length > 0" v-model="pagination.sortBy[0]"/>
                  <input type="hidden" name="perPage" v-if="pagination.itemsPerPage" v-model="pagination.itemsPerPage"/>
                  <input type="hidden" name="page" v-if="pagination.page" v-model="pagination.page"/>
                  <input type="hidden" name="sortDesc" v-if="pagination.sortDesc.length > 0 && pagination.sortDesc[0]" v-model="pagination.sortDesc[0]"/>
                  <v-text-field
                      name="q"
                      :value="oldsearch"
                      append-icon="mdi-magnify"
                      label="Search"
                      single-line
                      hide-details
                      @click:append="searchItems"
                  ></v-text-field>
              </form>
          </v-toolbar>
      </template>
  </v-data-table>
</template>

<script>
export default {
  name: 'TableComponent',
  props: [
      'items',
      'headers',
      'routes',
      'csrftoken',
      'searchable',
      'oldsearch',
      'sortby',
      'sortdesc'
  ],
  data() {
    return {
        pagination:
            {
                page: this.items.current_page,
                itemsPerPage: parseInt(this.items.per_page),
                sortBy: this.sortby !== '' ? [this.sortby]: [],
                sortDesc: this.sortdesc !== '' ? [!Boolean(parseInt(this.sortdesc))]: [],
            }
    }
  },
  methods: {
      deleteItem(id) {
          this.$refs[`delete-form-${id}`].submit()
      },
      searchItems() {
          let searchForm = this.$refs["search-form"];
          if (searchForm) {
              setTimeout(function () {
                  searchForm.submit()
              },400)
          }
      }
  },
}
</script>

<style scoped>

</style>
