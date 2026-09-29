<x-admin.import-excel-modal
    :show="$showImportModal"
    title="Import Soal SKB"
    description="Unduh template Excel, isi data soal, lalu unggah file di bawah."
    :form-action="route('admin.jabatan-skb.soal.import', $jabatanSkb)"
    :template-route="route('admin.jabatan-skb.soal.import-template', $jabatanSkb)"
    max-size="50 MB"
>
    Soal yang diimpor otomatis masuk ke bank soal jabatan <strong>{{ $jabatanSkb->name }}</strong>. Import lebih dari 100 baris diproses di background (queue worker harus aktif).
</x-admin.import-excel-modal>
