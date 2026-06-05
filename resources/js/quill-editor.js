import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import katex from 'katex';
import 'katex/dist/katex.min.css';

window.katex = katex;

function registerQuillEditor() {
    Alpine.data('quillEditor', (content) => ({
        content,
        quill: null,

        init() {
            this.quill = new Quill(this.$refs.editor, {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ header: [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['link', 'image'],
                        ['clean'],
                    ],
                },
            });

            if (this.content) {
                this.quill.root.innerHTML = this.content;
            }

            this.quill.on('text-change', () => {
                this.content = this.quill.root.innerHTML;
            });

            this.$watch('content', (value) => {
                if (this.quill && value !== this.quill.root.innerHTML) {
                    this.quill.root.innerHTML = value ?? '';
                }
            });
        },
    }));
}

if (window.Alpine) {
    registerQuillEditor();
} else {
    document.addEventListener('alpine:init', registerQuillEditor);
}

export default {};
