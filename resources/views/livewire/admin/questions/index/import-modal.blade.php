<x-admin.import-excel-modal
    :show="$showImportModal"
    title="Import Soal"
    description="Unduh template Excel, isi data soal, lalu unggah file di bawah."
    :form-action="route('admin.questions.import')"
    :template-route="route('admin.questions.import-template')"
    max-size="10 MB"
>
    Sheet <strong>Template Soal</strong> berisi contoh TWK &amp; TKP. Sheet <strong>Referensi Materi</strong> berisi daftar <code>material_slug</code> yang valid.
</x-admin.import-excel-modal>
