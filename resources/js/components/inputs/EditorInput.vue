<template>
    <div class="py-4 fullEditor-container">
        <div ref="fullEditor"></div>
    </div>
</template>

<script>
import Quill from 'quill';
import "quill/dist/quill.core.css";
import "quill/dist/quill.bubble.css";
import "quill/dist/quill.snow.css";
export default {
    name: "EditorInput",
    props: {
        value: {
            type: Object,
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
        },
    },
    data() {
        return {
            quill: null,
        };
    },
    mounted() {
        let _this = this;
        this.quill = new Quill(this.$refs.fullEditor, {
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['clean']
                ],
                history: {
                    delay: 2000,
                    maxStack: 500,
                    userOnly: true
                }
            },
            theme: "snow",
            formats: ["bold", "underline", "header", "italic", "link", 'align'],
            placeholder: this.label,
        });
        this.quill.root.innerHTML = this.value;
        this.quill.on("text-change", function () {
            return _this.update();
        });
    },
    methods: {
        update: function update() {
            this.$emit(
                "input",
                this.quill.getText() ? this.quill.root.innerHTML : ""
            );
        },
    },
}
</script>

<style scoped>
.fullEditor-container .ql-container {
    cursor: text;
    border-top: 1px;
    border-color: #9e9e9e;
    border-bottom-left-radius: 4px;
    border-bottom-right-radius: 4px;
}
.fullEditor-container .ql-container .ql-blank {
    min-height: 200px;
}
.ql-toolbar.ql-snow{
    border-top-left-radius: 4px;
    border-top-right-radius: 4px;
    border-color: #9e9e9e;
    text-decoration: underline;
}
.fullEditor-container:hover .ql-container {
    border-color: rgba(0,0,0,0.87);
}
.fullEditor-container:hover .ql-toolbar.ql-snow{
    border-color: rgba(0,0,0,0.87);

}
.ql-editor.ql-blank::before{
    font-size: 16px;
    color: #9e9e9e;
    font-style: normal;
}
</style>
