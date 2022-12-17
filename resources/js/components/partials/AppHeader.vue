<template>
  <v-app-bar app color="primary" dark>
    <v-spacer/>
    <v-menu
      offset-y
    >
      <template v-slot:activator="{ on, attrs }">
        <v-btn
          icon
          v-bind="attrs"
          v-on="on"
        >
          <v-icon>mdi-dots-vertical</v-icon>
        </v-btn>
      </template>

      <v-list nav>
        <v-list-item two-line>
          <v-list-item-content>
            <v-list-item-title>{{username}}</v-list-item-title>
            <v-list-item-subtitle>{{ email }}</v-list-item-subtitle>
          </v-list-item-content>
        </v-list-item>
        <v-divider/>
        <v-list-item link>
            <v-list-item-title @click="logout">Logout</v-list-item-title>
            <form ref="logout-form" :action="logoutroute" method="POST" style="display: none;">
                <input type="hidden" name="_token" :value="csrftoken"/>
            </form>
        </v-list-item>
      </v-list>
    </v-menu>
  </v-app-bar>
</template>

<script>
export default {
    name: 'AppHeader',
    props: {
        username: {
            type: String,
            default: ''
        },
        email: {
            type: String,
            default: ''
        },
        logoutroute: {
            type: String,
            default: ''
        },
        csrftoken: {
            type: String,
            default:''
        }
    },
    methods: {
        logout() {
            this.$refs["logout-form"].submit();
        }
    }
}
</script>

<style scoped>

</style>
