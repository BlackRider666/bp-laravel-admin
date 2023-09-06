import Vue from 'vue'
import vuetify from './plugins/vuetify'

Vue.config.productionTip = false

new Vue({
    el:'#app',
    vuetify,
    components: {
        'crud-layout': require('./layout/CRUDLayout').default,
        'auth-layout': require('./layout/AuthLayout').default,
        'crud-items': require('./components/TableComponent').default,
        'app-header': require('./components/partials/AppHeader').default,
        'app-footer': require('./components/partials/AppFooter').default,
        'left-bar': require('./components/partials/LeftBar').default,
        'boolean-input': require('./components/inputs/BooleanInput').default,
        'select-input': require('./components/inputs/SelectInput').default,
        'string-input': require('./components/inputs/StringInput').default,
        'submit-input': require('./components/inputs/SubmitInput').default,
        'text-input': require('./components/inputs/TextInput').default,
        'file-input': require('./components/inputs/FileInput').default,
        'translatable-input': require('./components/inputs/TranslatableInput').default,
        'editor-input': require('./components/inputs/EditorInput').default,
        'translatable-editor-input': require('./components/inputs/TranslatableEditorInput').default,
    }
})
