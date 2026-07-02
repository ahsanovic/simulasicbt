import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import katex from 'katex';
import 'katex/dist/katex.min.css';

window.katex = katex;

const BaseImage = Quill.import('formats/image');

class StorageImage extends BaseImage {
    static sanitize(url) {
        if (typeof url === 'string' && (url.startsWith('/') || url.startsWith('http://') || url.startsWith('https://'))) {
            return url;
        }

        return BaseImage.sanitize(url);
    }
}

Quill.register(StorageImage, true);

function editorImageUrl(path) {
    if (typeof path !== 'string' || path === '') {
        return '';
    }

    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }

    return `${window.location.origin}${path.startsWith('/') ? path : `/${path}`}`;
}

function insertImage(quill, index, path) {
    const url = editorImageUrl(path);

    if (! url) {
        return false;
    }

    quill.insertEmbed(index, 'image', url, 'user');
    quill.setSelection(index + 1);

    return true;
}

function syncContent(quill, wire) {
    wire.$set('content', quill.root.innerHTML, false);
}

window.syncQuestionContentEditor = function syncQuestionContentEditor(wire) {
    const editorEl = document.getElementById('question-content-editor');

    return editorEl?.__questionQuill?.root?.innerHTML ?? '';
};

window.saveQuestionForm = async function saveQuestionForm(wire) {
    const content = window.syncQuestionContentEditor(wire);

    await wire.save(content);
};

function pickImage(quill, wire) {
    const input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', 'image/jpeg,image/png,image/webp,image/gif');
    input.click();

    input.onchange = () => {
        const file = input.files?.[0];

        if (! file) {
            return;
        }

        const range = quill.getSelection(true) ?? { index: Math.max(0, quill.getLength() - 1), length: 0 };

        wire.$upload(
            'editorImage',
            file,
            async () => {
                try {
                    const path = await wire.processEditorImage();

                    if (! insertImage(quill, range.index, path)) {
                        window.alert('URL gambar tidak valid.');

                        return;
                    }

                    syncContent(quill, wire);
                } catch (error) {
                    const message = error?.message
                        ?? Object.values(error?.errors ?? {})[0]?.[0]
                        ?? 'Gagal mengunggah gambar.';

                    window.alert(message);
                }
            },
            () => {
                window.alert('Gagal mengunggah gambar.');
            },
        );
    };
}

window.initQuestionContentEditor = function initQuestionContentEditor(editorEl, wire) {
    if (! editorEl || ! wire) {
        return null;
    }

    editorEl.innerHTML = '';

    const quill = new Quill(editorEl, {
        theme: 'snow',
        modules: {
            toolbar: {
                container: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link', 'image'],
                    ['clean'],
                ],
                handlers: {
                    image() {
                        pickImage(quill, wire);
                    },
                },
            },
        },
    });

    const initial = wire.$get('content') || '';

    let isBooting = true;

    if (initial !== '') {
        quill.root.innerHTML = initial;
    }

    isBooting = false;

    quill.on('text-change', () => {
        if (isBooting) {
            return;
        }

        syncContent(quill, wire);
    });

    editorEl.__questionQuill = quill;

    return quill;
};

export default {};
