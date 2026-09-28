# Soal SKB — Design Spec

Date: 2026-09-28
Status: Approved for planning
Sub-project 1 of "Ujian Penuh" (SKD + SKB). This spec covers only the SKB
question bank. Exam type "SKB", peserta-per-jabatan, and the merged
"Ujian Penuh" flow are separate future specs.

## Goal

Let admin create and manage a question bank for SKB (Seleksi Kompetensi
Bidang), where each **Jabatan** (position) owns its own exclusive set of
questions — unlike SKD, where TWK/TIU/TKP subjects are shared across all
users.

## Non-goals (explicitly out of scope)

- Exam type "SKB" on the `Exam` model.
- Any exam-taking flow, attempt scoring, or certificate for SKB.
- Peserta/participant management scoped to a jabatan.
- Any change to the existing `Formation` model, `Subject`,
  `Question`, `QuestionOption`, or their admin UI. SKD stays untouched.

## Why new models instead of reusing Question/Subject

Decided with the user: SKB gets fully separate models
(`JabatanSkb`, `SkbQuestion`, `SkbQuestionOption`, ...). Reasons:

- `Formation` already means something different in this codebase (used
  for SKD matchmaking/recap, nav label "Kelola Jabatan"). Reusing it for
  SKB's jabatan concept would conflate two unrelated features.
- `Subject` is a closed enum (`twk`/`tiu`/`tkp`) with SKD-specific
  branches (weighted scoring for TKP). SKB has one subject per jabatan
  and single-correct-answer scoring only — forcing it through `Subject`
  would add conditionals to code that must stay stable for SKD.
- Full separation means zero regression risk to the existing,
  already-shipped SKD question bank.

## Data model

### `jabatan_skbs`

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| name | string, unique | e.g. "Analis Kebijakan" |
| slug | string, unique | derived from name |
| description | text, nullable | |
| is_active | boolean, default true | inactive jabatan hidden from question-bank picker |
| created_by | FK users, nullable | |
| timestamps, soft deletes | | |

Model `App\Models\JabatanSkb`:
- `questions(): HasMany` → `SkbQuestion`
- `importJobs(): HasMany` → `SkbQuestionImportJob`

### `skb_questions`

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| jabatan_skb_id | FK jabatan_skbs | cascade on delete |
| content | longtext (HTML, sanitized) | |
| explanation | longtext, nullable | |
| difficulty | string enum: easy/medium/hard | default medium |
| is_active | boolean, default true | |
| created_by | FK users, nullable | |
| timestamps, soft deletes | | |

Model `App\Models\SkbQuestion`:
- `jabatanSkb(): BelongsTo`
- `options(): HasMany` → `SkbQuestionOption`, ordered by `sort_order`
- `correctOption(): ?SkbQuestionOption`

### `skb_question_options`

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| skb_question_id | FK skb_questions | cascade on delete |
| label | string(1) | A-E |
| content_type | string enum, reuse `App\Enums\QuestionOptionContentType` | text/image |
| content | text, nullable | required when content_type=text |
| image_path | string, nullable | required when content_type=image, path under `question-options/` disk `public` (reuse existing storage convention) |
| is_correct | boolean, default false | exactly one true per question |
| sort_order | tinyint | 1-5 |
| timestamps | | |

No `score_weight` column — SKB is single-correct-answer only, no TKP-style
weighting.

Model `App\Models\SkbQuestionOption`: `belongsTo(SkbQuestion::class)`.

### `skb_question_import_jobs`

Mirrors `question_import_jobs` with one addition:

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| jabatan_skb_id | FK jabatan_skbs | which jabatan this import targets |
| user_id | FK users | |
| total_rows / processed_rows | int | |
| status | string enum, reuse `App\Enums\QuestionImportStatus` | |
| error_message | text, nullable | |
| started_at / completed_at | timestamp, nullable | |
| timestamps | | |

Model `App\Models\SkbQuestionImportJob`: same shape as
`QuestionImportJob` (progress-percent via cache key
`skb-import-progress-{id}`, `markProcessing`/`advance`/`markCompleted`/`markFailed`).

## Import pipeline

Mirrors `app/Imports/Questions*.php` and `QuestionImportService`, minus
subject/material resolution (target jabatan is chosen in the UI before
upload, not per-row).

Template sheet "Template Soal SKB", header row columns:

`content | explanation | difficulty | option_a | option_b | option_c | option_d | option_e | correct_option`

No `weight_*` columns.

New files:
- `app/Imports/SkbQuestionsSheetImport.php` — `ToCollection`,
  `WithChunkReading` (100), `WithHeadingRow`. Constructor takes
  `jabatanSkbId`, `createdBy`, `importJobId`. Creates `SkbQuestion` +
  5 `SkbQuestionOption` rows per data row inside a `DB::transaction`,
  sanitizing `content`/`explanation`/option content via
  `HtmlSanitizer` (reuse existing service).
- `app/Imports/SkbQuestionsQueuedImport.php` — queued wrapper, same
  pattern as `QuestionsQueuedImport`.
- `app/Imports/SkbQuestionsImportValidator.php` — sync validation pass
  before queueing (required columns, correct_option in A-E, difficulty
  in easy/medium/hard), same pattern as `QuestionsImportValidator`.
- `app/Imports/SkbQuestionsRowCounter.php` — counts data rows for the
  100-row background threshold.
- Extract or duplicate the row-filtering logic from
  `ValidatesQuestionImportRows` into a small SKB-specific trait/concern
  (`ValidatesSkbQuestionImportRows`) since the row shape differs
  (no subject_code/material_slug/weight columns).

New service: `app/Services/SkbQuestionImportService.php`, same shape as
`QuestionImportService::import()` but takes `jabatanSkbId` and creates
`SkbQuestionImportJob` scoped to that jabatan. Same ≤100-rows-sync /
>100-rows-queued threshold (`BACKGROUND_ROW_THRESHOLD = 100`).

New controller: `App\Http\Controllers\Admin\SkbQuestionImportController::store()`,
mirrors `QuestionImportController::store()` — validates uploaded file,
stores to local disk, calls `SkbQuestionImportService::import()`,
redirects back with flash message. Also
`SkbQuestionContentImageController::store()` mirrors
`QuestionContentImageController::store()` for the rich-text editor's
inline image upload (stores under `skb-question-content/` disk
`public`).

## Admin UI

### Jabatan SKB management

Route: `GET /admin/jabatan-skb` → `admin.jabatan-skb.index`
Component: `App\Livewire\Admin\JabatanSkb\Index` (`#[Layout('layouts.admin')]`)

CRUD list + modal form: name (slug auto-generated on create, editable),
description, is_active toggle. Delete = soft delete, blocked (flash
error) if the jabatan still has questions — mirrors how `Formations\Index`
guards against deleting a formation in use, adapted to check
`$jabatan->questions()->exists()`.

Table shows: name, question count, is_active, actions (edit, delete,
"Kelola Soal" link → question list for that jabatan).

### Soal SKB management

Route: `GET /admin/jabatan-skb/{jabatanSkb}/soal` → `admin.jabatan-skb.soal.index`
Component: `App\Livewire\Admin\JabatanSkb\SoalIndex`, mounted with the
route-model-bound `JabatanSkb`.

Mirrors `App\Livewire\Admin\Questions\Index` structure and sub-views
(`table`, `form-modal`, `preview-modal`, `import-modal`,
`import-progress`, `header` partials under
`resources/views/livewire/admin/jabatan-skb/soal-index/`), with these
differences from the SKD version:
- No subject/material fields or filters — every question on this page
  belongs to the bound jabatan.
- No TKP branch: exactly one correct option, no score_weight input.
- Import modal posts to the SKB import route/controller and passes the
  bound jabatan's id.
- Page title / breadcrumb shows the jabatan name.

### Routes (added to `routes/web.php`, inside existing `admin` group)

```
Route::get('/jabatan-skb', JabatanSkbIndex::class)->name('jabatan-skb.index');
Route::get('/jabatan-skb/{jabatanSkb}/soal', SkbSoalIndex::class)->name('jabatan-skb.soal.index');
Route::post('/jabatan-skb/{jabatanSkb}/soal/upload-image', [SkbQuestionContentImageController::class, 'store'])->name('jabatan-skb.soal.upload-image');
Route::post('/jabatan-skb/{jabatanSkb}/soal/import', [SkbQuestionImportController::class, 'store'])->name('jabatan-skb.soal.import');
```

### Navigation

Add to `resources/views/components/admin/sidebar-nav.blade.php`, in the
existing "Konten Ujian" group, right after "Bank Soal":

```php
['route' => 'admin.jabatan-skb.index', 'label' => 'Soal SKB', 'icon' => 'questions'],
```

Labeled "Soal SKB" (not "Jabatan") to avoid confusion with the existing
"Kelola Jabatan" nav item, which manages `Formation` (a different,
unrelated concept despite the similar name).

## Validation rules

`JabatanSkb\Index::rules()`:
- `name`: required, string, max 255, unique (ignoring current id on edit)
- `description`: nullable, string
- `is_active`: boolean

`JabatanSkb\SoalIndex::rules()` (per question, same shape as
`Admin\Questions\Index::rules()` minus subject/material/weight):
- `content`: required, string
- `explanation`: nullable, string
- `difficulty`: required, in easy/medium/hard
- `is_active`: boolean
- `options`: required array min:2
- `options.*.label`, `options.*.content_type`, `options.*.content`,
  `options.*.image_path`: same rules as existing `Questions\Index`
- exactly one `is_correct` (enforced via `correctOptionIndex`, same
  pattern as existing component)

## Testing plan

- Feature test: admin can create/edit/delete a `JabatanSkb`; delete
  blocked when it has questions.
- Feature test: admin can create/edit/delete a `SkbQuestion` with
  text and image options under a jabatan; correct-option enforcement.
- Feature test: import flow — small file (sync) creates questions
  under the right jabatan; oversized file (>100 rows) queues a
  `SkbQuestionImportJob` and processes it; invalid file surfaces a
  validation error report.
- Unit test: `SkbQuestion::correctOption()`.

## Future work (separate specs)

1. Add `ExamType` (SKD/SKB) to `Exam`, letting an SKB exam pull its
   question set from a `JabatanSkb`'s `SkbQuestion` bank instead of
   the `exam_questions` pivot.
2. Peserta-per-jabatan: scope event/exam participants to a jabatan so
   each peserta only sits the SKB exam for their assigned jabatan.
3. "Ujian Penuh": combine SKD + SKB attempts into one flow/report per
   peserta.
