<x-admin.import-excel-modal
    :show="$showImportModal"
    title="Import Peserta"
    description="Unduh template Excel, isi data peserta, lalu unggah file di bawah."
    :form-action="route('admin.users.import')"
    :template-route="route('admin.users.import-template')"
    max-size="20 MB"
>
    Kolom: <strong>name</strong>, <strong>email</strong>, <strong>username</strong>, <strong>password</strong>, <strong>nip</strong>, <strong>instansi_id</strong>, <strong>is_pegawai</strong> (1/0). Import lebih dari 50 baris diproses di background.
</x-admin.import-excel-modal>
