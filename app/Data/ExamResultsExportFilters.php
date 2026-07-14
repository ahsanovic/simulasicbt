<?php

namespace App\Data;

final class ExamResultsExportFilters
{
    public function __construct(
        public string $search = '',
        public string $examTypeFilter = '',
        public string $dateFrom = '',
        public string $dateTo = '',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            search: (string) ($data['search'] ?? ''),
            examTypeFilter: (string) ($data['exam_type_filter'] ?? ''),
            dateFrom: (string) ($data['date_from'] ?? ''),
            dateTo: (string) ($data['date_to'] ?? ''),
        );
    }

    /** @return array{search: string, exam_type_filter: string, date_from: string, date_to: string} */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'exam_type_filter' => $this->examTypeFilter,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];
    }
}
