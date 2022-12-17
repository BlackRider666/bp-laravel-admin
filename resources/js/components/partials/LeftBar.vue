<template>
  <v-navigation-drawer
    v-model="drawer"
    :mini-variant.sync="mini"
    expand-on-hover
    app
    color="#f5f5f5"
  >
    <v-list-item class="px-2 py-1 primary" dark>
      <v-list-item-avatar>
        <v-avatar color="white" class="text--secondary">
            <v-img v-if="logo" :src="logo"></v-img>
            <template v-else> <span>{{title.slice(0,3)}}</span></template>
        </v-avatar>
      </v-list-item-avatar>

      <v-list-item-title>{{title}}</v-list-item-title>
    </v-list-item>

    <v-divider></v-divider>

    <v-list dense>
      <template v-for="[key,item] of Object.entries(items)">
        <template v-if="item.items">
          <v-list-group
            :key="item.title"
            v-model="item.active"
            :prepend-icon="item.icon"
          >
            <template v-slot:activator>
              <v-list-item-content>
                <v-list-item-title v-text="item.title"></v-list-item-title>
              </v-list-item-content>
            </template>

            <v-list-item
              v-for="[key,child] of Object.entries(item.items)"
              :key="child.title"
              link
              :href="child.href"
              :class="child.active?'v-list-item--active':''"
            >
              <v-list-item-icon><v-icon>{{ child.icon }}</v-icon></v-list-item-icon>
              <v-list-item-title v-text="child.title"></v-list-item-title>
            </v-list-item>
          </v-list-group>
        </template>
        <template v-else>
          <v-list-item
            :key="item.title"
            link
            :href="item.href"
            :class="item.active?'v-list-item--active primary--text':''"
          >
            <v-list-item-icon><v-icon>{{ item.icon }}</v-icon></v-list-item-icon>
            <v-list-item-title v-text="item.title"></v-list-item-title>
          </v-list-item>
        </template>
      </template>
    </v-list>
  </v-navigation-drawer>
</template>

<script>
export default {
  name: 'LeftBar',
    props: {
        items: {
            type: Object,
        },
        logo: {
            type: String,
            default: null,
        },
        title: {
            type: String,
            default: ''
        }
    },
  data () {
    return {
      drawer: true,
      mini: true
    }
  }
}
</script>

<style scoped>

</style>
