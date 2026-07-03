<?php

namespace App\Services;

use App\Enums\SubjectCode;

class GeneratedQuestionValidator
{
    public function validate(array $question, SubjectCode $subjectCode): ?string
    {
        if (trim(strip_tags($question['content'] ?? '')) === '') {
            return 'Isi soal wajib diisi.';
        }

        if (trim(strip_tags($question['explanation'] ?? '')) === '') {
            return 'Pembahasan wajib diisi.';
        }

        $options = $question['options'] ?? [];

        if (count($options) < 2) {
            return 'Soal harus memiliki minimal 2 pilihan jawaban.';
        }

        foreach ($options as $option) {
            if (trim($option['content'] ?? '') === '') {
                return 'Semua pilihan jawaban wajib diisi.';
            }
        }

        if ($subjectCode === SubjectCode::Tkp) {
            return $this->validateTkpOptions($options);
        }

        return $this->validateNonTkpOptions($options, (int) ($question['correct_option_index'] ?? -1));
    }

    public function validateTkpOptions(array $options): ?string
    {
        if (count($options) !== 5) {
            return 'Soal TKP harus memiliki tepat 5 pilihan jawaban (A–E).';
        }

        foreach ($options as $option) {
            if (($option['is_correct'] ?? false) === true) {
                return 'Pada soal TKP, tidak ada opsi yang ditandai benar/salah.';
            }
        }

        $weights = array_map(fn ($option) => (int) ($option['score_weight'] ?? 0), $options);

        foreach ($weights as $weight) {
            if ($weight < 1 || $weight > 5) {
                return 'Bobot TKP harus bernilai 1 sampai 5.';
            }
        }

        if (count($weights) !== count(array_unique($weights))) {
            return 'Pada soal TKP, bobot setiap opsi tidak boleh duplikat.';
        }

        $sorted = array_values($weights);
        sort($sorted);

        if ($sorted !== [1, 2, 3, 4, 5]) {
            return 'Pada soal TKP, bobot harus unik dan berisi angka 1, 2, 3, 4, dan 5.';
        }

        return null;
    }

    public function validateNonTkpOptions(array $options, int $correctOptionIndex): ?string
    {
        if (count($options) !== 5) {
            return 'Soal TWK/TIU harus memiliki tepat 5 pilihan jawaban (A–E).';
        }

        if ($correctOptionIndex < 0 || $correctOptionIndex >= count($options)) {
            return 'Jawaban benar wajib dipilih.';
        }

        return null;
    }
}
