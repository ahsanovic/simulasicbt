# Soal SKB Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let admin manage a per-jabatan SKB question bank (jabatan CRUD, question CRUD with text/image options, Excel import) — fully separate from the existing SKD (TWK/TIU/TKP) question bank.

**Architecture:** New tables/models (`JabatanSkb`, `SkbQuestion`, `SkbQuestionOption`, `SkbQuestionImportJob`) mirror the existing `Question`/`QuestionOption`/`QuestionImportJob` shape but drop subject/material/TKP-weighting concepts. Two new admin Livewire pages (jabatan CRUD, then a per-jabatan soal CRUD) reuse the existing design-system Blade components, the Quill editor JS, `HtmlSanitizer`, `ImportErrorReport`, and the generic `x-admin.import-excel-modal` component verbatim — no changes to any SKD file.

**Tech Stack:** Laravel 13, Livewire 3, Maatwebsite Excel, Tailwind (existing `ui-*` utility classes), Quill (existing `resources/js/quill-editor.js`), PHPUnit (sqlite in-memory).

**Spec:** `docs/superpowers/specs/2026-09-28-soal-skb-design.md`

## Global Constraints

- Do not modify `Formation`, `Subject`, `Question`, `QuestionOption`, `QuestionImportJob`, or any of their admin Livewire/Blade files. SKD stays untouched.
- SKB questions have exactly one correct option, no TKP-style `score_weight`.
- Reuse verbatim (no forks/copies with modifications): `App\Enums\QuestionOptionContentType`, `App\Enums\QuestionImportStatus`, `App\Services\HtmlSanitizer`, `App\Support\ImportErrorReport`, `App\Exceptions\ImportFailedException`, `App\Livewire\Concerns\HandlesImportErrorModal`, `App\Imports\Concerns\DeletesStoredImportFile`, the `x-admin.import-excel-modal` and `x-ui.import-error-modal` Blade components, and `resources/js/quill-editor.js` (editor element id must stay `question-content-editor` for the existing JS to bind).
- Route group: all new routes live inside the existing `Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group()` block in `routes/web.php`, so they inherit the `auth`+`admin` middleware and get the `admin.` route-name prefix automatically.
- Follow existing UI conventions: `x-ui.page-header`, `x-ui.flash-toast`, `x-ui.filter-toolbar`, `ui-table-wrap`, `ui-input`, `ui-select`, `ui-label`, `ui-badge`, `ui-btn-primary`, `ui-btn-secondary`, `ui-btn-ghost` classes.
- Do not commit or push. Per user instruction, leave all changes in the working tree uncommitted/local only — skip every "Commit" step below (listed only so the diff boundary per task is clear).

---

## File Structure

```
app/
  Models/
    JabatanSkb.php                        (new)
    SkbQuestion.php                        (new)
    SkbQuestionOption.php                  (new)
    SkbQuestionImportJob.php               (new)
  Livewire/Admin/JabatanSkb/
    Index.php                              (new — jabatan CRUD)
    SoalIndex.php                          (new — soal CRUD + import wiring)
  Imports/
    SkbQuestionsSheetImport.php            (new)
    SkbQuestionsImport.php                 (new — sync wrapper)
    SkbQuestionsQueuedImport.php           (new — background wrapper)
    SkbQuestionsImportValidator.php        (new — validation-only pass)
    SkbQuestionsRowCounter.php             (new)
    Concerns/ValidatesSkbQuestionImportRows.php (new)
  Services/
    SkbQuestionImportService.php           (new)
  Http/Controllers/Admin/
    SkbQuestionImportController.php        (new)
  Exports/
    SkbQuestionsImportTemplate.php         (new)
database/migrations/
  2026_09_28_100000_create_jabatan_skbs_table.php            (new)
  2026_09_28_100001_create_skb_questions_table.php           (new)
  2026_09_28_100002_create_skb_question_options_table.php    (new)
  2026_09_28_100003_create_skb_question_import_jobs_table.php (new)
resources/views/livewire/admin/jabatan-skb/
  index.blade.php                         (new — jabatan CRUD page)
  soal-index.blade.php                    (new — soal CRUD page shell)
  soal-index/
    table.blade.php                       (new)
    form-modal.blade.php                  (new)
    preview-modal.blade.php               (new)
    import-modal.blade.php                (new)
    import-progress.blade.php             (new)
routes/web.php                             (modify — add routes)
resources/views/components/admin/sidebar-nav.blade.php (modify — add nav item)
tests/
  Unit/SkbQuestionTest.php                          (new)
  Feature/Admin/JabatanSkbIndexTest.php              (new)
  Feature/Admin/SkbSoalIndexTest.php                 (new)
  Feature/Admin/SkbQuestionImportTest.php            (new)
  Feature/Admin/SkbSoalImportUiTest.php              (new)
```

---

### Task 1: Migrations + Eloquent models

**Files:**
- Create: `database/migrations/2026_09_28_100000_create_jabatan_skbs_table.php`
- Create: `database/migrations/2026_09_28_100001_create_skb_questions_table.php`
- Create: `database/migrations/2026_09_28_100002_create_skb_question_options_table.php`
- Create: `database/migrations/2026_09_28_100003_create_skb_question_import_jobs_table.php`
- Create: `app/Models/JabatanSkb.php`
- Create: `app/Models/SkbQuestion.php`
- Create: `app/Models/SkbQuestionOption.php`
- Create: `app/Models/SkbQuestionImportJob.php`
- Test: `tests/Unit/SkbQuestionTest.php`

**Interfaces:**
- Produces: `JabatanSkb::questions(): HasMany<SkbQuestion>`, `JabatanSkb::importJobs(): HasMany<SkbQuestionImportJob>`; `SkbQuestion::jabatanSkb(): BelongsTo<JabatanSkb>`, `SkbQuestion::options(): HasMany<SkbQuestionOption>` (ordered by `sort_order`), `SkbQuestion::correctOption(): ?SkbQuestionOption`; `SkbQuestionOption::question(): BelongsTo<SkbQuestion>`; `SkbQuestionImportJob::jabatanSkb(): BelongsTo<JabatanSkb>`, `SkbQuestionImportJob::user(): BelongsTo<User>`, `SkbQuestionImportJob::isStale(): bool`, `SkbQuestionImportJob::markProcessing(): void`, `SkbQuestionImportJob::advance(int $rows): void`, `SkbQuestionImportJob::markCompleted(): void`, `SkbQuestionImportJob::markFailed(string $message): void`, `SkbQuestionImportJob::progressPercent(): int`. These exact names are used by every later task.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/SkbQuestionTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Enums\QuestionOptionContentType;
use App\Models\JabatanSkb;
use App\Models\SkbQuestion;
use App\Models\SkbQuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkbQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_jabatan_skb_has_many_questions(): void
    {
        $jabatan = JabatanSkb::query()->create([
            'name' => 'Analis Kebijakan',
            'slug' => 'analis-kebijakan',
            'is_active' => true,
        ]);

        SkbQuestion::query()->create([
            'jabatan_skb_id' => $jabatan->id,
            'content' => '<p>Soal 1</p>',
            'difficulty' => 'medium',
            'is_active' => true,
        ]);

        $this->assertCount(1, $jabatan->fresh()->questions);
    }

    public function test_options_are_ordered_by_sort_order(): void
    {
        $jabatan = JabatanSkb::query()->create([
            'name' => 'Auditor',
            'slug' => 'auditor',
            'is_active' => true,
        ]);

        $question = SkbQuestion::query()->create([
            'jabatan_skb_id' => $jabatan->id,
            'content' => '<p>Soal urutan</p>',
            'difficulty' => 'medium',
            'is_active' => true,
        ]);

        SkbQuestionOption::query()->create([
            'skb_question_id' => $question->id,
            'label' => 'B',
            'content_type' => QuestionOptionContentType::Text,
            'content' => 'Pilihan B',
            'is_correct' => false,
            'sort_order' => 2,
        ]);

        SkbQuestionOption::query()->create([
            'skb_question_id' => $question->id,
            'label' => 'A',
            'content_type' => QuestionOptionContentType::Text,
            'content' => 'Pilihan A',
            'is_correct' => true,
            'sort_order' => 1,
        ]);

        $labels = $question->fresh()->options->pluck('label')->all();

        $this->assertSame(['A', 'B'], $labels);
    }

    public function test_correct_option_returns_the_flagged_option(): void
    {
        $jabatan = JabatanSkb::query()->create([
            'name' => 'Pustakawan',
            'slug' => 'pustakawan',
            'is_active' => true,
        ]);

        $question = SkbQuestion::query()->create([
            'jabatan_skb_id' => $jabatan->id,
            'content' => '<p>Soal benar</p>',
            'difficulty' => 'medium',
            'is_active' => true,
        ]);

        SkbQuestionOption::query()->create([
            'skb_question_id' => $question->id,
            'label' => 'A',
            'content_type' => QuestionOptionContentType::Text,
            'content' => 'Pilihan A',
            'is_correct' => false,
            'sort_order' => 1,
        ]);

        $correct = SkbQuestionOption::query()->create([
            'skb_question_id' => $question->id,
            'label' => 'B',
            'content_type' => QuestionOptionContentType::Text,
            'content' => 'Pilihan B',
            'is_correct' => true,
            'sort_order' => 2,
        ]);

        $this->assertSame($correct->id, $question->fresh()->correctOption()->id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SkbQuestionTest`
Expected: FAIL — class `App\Models\JabatanSkb` not found (migrations/models don't exist yet).

- [ ] **Step 3: Create the migrations**

`database/migrations/2026_09_28_100000_create_jabatan_skbs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jabatan_skbs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jabatan_skbs');
    }
};
```

`database/migrations/2026_09_28_100001_create_skb_questions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skb_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jabatan_skb_id')->constrained('jabatan_skbs')->cascadeOnDelete();
            $table->longText('content');
            $table->longText('explanation')->nullable();
            $table->string('difficulty')->default('medium');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skb_questions');
    }
};
```

`database/migrations/2026_09_28_100002_create_skb_question_options_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skb_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skb_question_id')->constrained('skb_questions')->cascadeOnDelete();
            $table->string('label', 1);
            $table->string('content_type')->default('text');
            $table->text('content')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skb_question_options');
    }
};
```

`database/migrations/2026_09_28_100003_create_skb_question_import_jobs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skb_question_import_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jabatan_skb_id')->constrained('jabatan_skbs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skb_question_import_jobs');
    }
};
```

- [ ] **Step 4: Create the models**

`app/Models/JabatanSkb.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JabatanSkb extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SkbQuestion::class);
    }

    public function importJobs(): HasMany
    {
        return $this->hasMany(SkbQuestionImportJob::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

`app/Models/SkbQuestion.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SkbQuestion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'jabatan_skb_id',
        'content',
        'explanation',
        'difficulty',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function jabatanSkb(): BelongsTo
    {
        return $this->belongsTo(JabatanSkb::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(SkbQuestionOption::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function correctOption(): ?SkbQuestionOption
    {
        return $this->options->firstWhere('is_correct', true);
    }
}
```

`app/Models/SkbQuestionOption.php`:

```php
<?php

namespace App\Models;

use App\Enums\QuestionOptionContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkbQuestionOption extends Model
{
    protected $fillable = [
        'skb_question_id',
        'label',
        'content_type',
        'content',
        'image_path',
        'is_correct',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'content_type' => QuestionOptionContentType::class,
            'is_correct' => 'boolean',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SkbQuestion::class, 'skb_question_id');
    }
}
```

`app/Models/SkbQuestionImportJob.php`:

```php
<?php

namespace App\Models;

use App\Enums\QuestionImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class SkbQuestionImportJob extends Model
{
    protected $fillable = [
        'jabatan_skb_id',
        'user_id',
        'total_rows',
        'processed_rows',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuestionImportStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function jabatanSkb(): BelongsTo
    {
        return $this->belongsTo(JabatanSkb::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isStale(): bool
    {
        if ($this->status !== QuestionImportStatus::Pending) {
            return false;
        }

        return $this->created_at->diffInMinutes(now()) >= 2;
    }

    public function markProcessing(): void
    {
        if ($this->status !== QuestionImportStatus::Pending) {
            return;
        }

        $this->update([
            'status' => QuestionImportStatus::Processing,
            'started_at' => now(),
        ]);
    }

    public function progressPercent(): int
    {
        if (Cache::has("skb-import-progress-{$this->id}")) {
            return (int) Cache::get("skb-import-progress-{$this->id}");
        }

        if ($this->total_rows === 0) {
            return 0;
        }

        if ($this->status === QuestionImportStatus::Completed) {
            return 100;
        }

        return min(99, (int) round(($this->processed_rows / $this->total_rows) * 100));
    }

    public function advance(int $rows): void
    {
        if ($rows <= 0) {
            return;
        }

        if ($this->status === QuestionImportStatus::Pending) {
            $this->markProcessing();
        }

        $this->increment('processed_rows', $rows);

        if ($this->total_rows > 0) {
            $percent = min(99, (int) round(($this->processed_rows / $this->total_rows) * 100));
            Cache::put("skb-import-progress-{$this->id}", $percent, now()->addMinutes(10));
        }
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => QuestionImportStatus::Completed,
            'processed_rows' => $this->total_rows,
            'completed_at' => now(),
        ]);

        Cache::forget("skb-import-progress-{$this->id}");
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => QuestionImportStatus::Failed,
            'error_message' => $message,
            'completed_at' => now(),
        ]);

        Cache::forget("skb-import-progress-{$this->id}");
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SkbQuestionTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit** (skipped per Global Constraints — keep changes local/uncommitted)

---

### Task 2: Admin Jabatan SKB CRUD

**Files:**
- Create: `app/Livewire/Admin/JabatanSkb/Index.php`
- Create: `resources/views/livewire/admin/jabatan-skb/index.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/components/admin/sidebar-nav.blade.php`
- Test: `tests/Feature/Admin/JabatanSkbIndexTest.php`

**Interfaces:**
- Consumes: `App\Models\JabatanSkb` (Task 1).
- Produces: route name `admin.jabatan-skb.index`. Task 3 links to a `admin.jabatan-skb.soal.index` route it will add itself — do not add that route or a "Kelola Soal" link in this task; the target page doesn't exist yet.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/JabatanSkbIndexTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Livewire\Admin\JabatanSkb\Index;
use App\Models\JabatanSkb;
use App\Models\SkbQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JabatanSkbIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_jabatan_skb_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.jabatan-skb.index'))
            ->assertOk()
            ->assertSee('Soal SKB');
    }

    public function test_peserta_cannot_access_jabatan_skb_page(): void
    {
        $peserta = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($peserta)
            ->get(route('admin.jabatan-skb.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_jabatan_skb(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('openCreateModal')
            ->set('name', 'Analis Kebijakan')
            ->set('description', 'Formasi analis kebijakan publik')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('jabatan_skbs', [
            'name' => 'Analis Kebijakan',
            'slug' => 'analis-kebijakan',
        ]);
    }

    public function test_admin_can_update_jabatan_skb(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create([
            'name' => 'Auditor',
            'slug' => 'auditor',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('openEditModal', $jabatan->id)
            ->set('name', 'Auditor Ahli')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('jabatan_skbs', [
            'id' => $jabatan->id,
            'name' => 'Auditor Ahli',
            'slug' => 'auditor-ahli',
        ]);
    }

    public function test_admin_cannot_delete_jabatan_skb_with_questions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create([
            'name' => 'Dokter',
            'slug' => 'dokter',
            'is_active' => true,
        ]);

        SkbQuestion::query()->create([
            'jabatan_skb_id' => $jabatan->id,
            'content' => '<p>Soal</p>',
            'difficulty' => 'medium',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('delete', $jabatan->id);

        $this->assertDatabaseHas('jabatan_skbs', ['id' => $jabatan->id]);
    }

    public function test_admin_can_delete_unused_jabatan_skb(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create([
            'name' => 'Pustakawan',
            'slug' => 'pustakawan',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('delete', $jabatan->id);

        $this->assertDatabaseMissing('jabatan_skbs', ['id' => $jabatan->id]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=JabatanSkbIndexTest`
Expected: FAIL — route `admin.jabatan-skb.index` and class `App\Livewire\Admin\JabatanSkb\Index` don't exist yet.

- [ ] **Step 3: Create the Livewire component**

`app/Livewire/Admin/JabatanSkb/Index.php`:

```php
<?php

namespace App\Livewire\Admin\JabatanSkb;

use App\Models\JabatanSkb;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Soal SKB — Jabatan')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('jabatan_skbs', 'name')->ignore($this->editingId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search']);
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $jabatanSkbId): void
    {
        $jabatan = JabatanSkb::query()->findOrFail($jabatanSkbId);
        $this->editingId = $jabatan->id;
        $this->name = $jabatan->name;
        $this->description = $jabatan->description ?? '';
        $this->is_active = $jabatan->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $data = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'is_active' => $validated['is_active'],
            'slug' => $this->uniqueSlug($validated['name'], $this->editingId),
        ];

        if ($this->editingId) {
            JabatanSkb::query()->findOrFail($this->editingId)->update($data);
        } else {
            JabatanSkb::query()->create([
                ...$data,
                'created_by' => auth()->id(),
            ]);
        }

        session()->flash('success', 'Jabatan SKB berhasil disimpan.');
        $this->closeModal();
    }

    public function delete(int $jabatanSkbId): void
    {
        $jabatan = JabatanSkb::query()->withCount('questions')->findOrFail($jabatanSkbId);

        if ($jabatan->questions_count > 0) {
            session()->flash('error', "Jabatan \"{$jabatan->name}\" tidak bisa dihapus karena masih memiliki {$jabatan->questions_count} soal.");

            return;
        }

        $jabatan->delete();

        session()->flash('success', 'Jabatan SKB berhasil dihapus.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $jabatanSkbs = JabatanSkb::query()
            ->withCount('questions')
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.jabatan-skb.index', compact('jabatanSkbs'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description']);
        $this->is_active = true;
        $this->resetValidation();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (JabatanSkb::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
```

- [ ] **Step 4: Create the view**

`resources/views/livewire/admin/jabatan-skb/index.blade.php`:

```blade
<div>
    <x-ui.page-header title="Soal SKB — Jabatan" description="Kelola daftar jabatan lalu isi bank soal SKB masing-masing jabatan.">
        <button wire:click="openCreateModal" class="ui-btn-primary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Jabatan
        </button>
    </x-ui.page-header>

    <x-ui.flash-toast />

    <div class="ui-card mb-5 p-4 sm:p-5">
        <x-ui.filter-toolbar>
            <div class="relative min-w-0 w-full sm:max-w-md sm:flex-1">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama jabatan..." class="ui-input pl-10">
            </div>
        </x-ui.filter-toolbar>
    </div>

    <div class="ui-table-wrap">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/80">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jabatan</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Slug</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Soal</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($jabatanSkbs as $jabatan)
                        <tr wire:key="jabatan-skb-{{ $jabatan->id }}" class="transition hover:bg-slate-50/50">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">{{ $jabatan->name }}</p>
                                @if ($jabatan->description)
                                    <p class="text-xs text-slate-500">{{ $jabatan->description }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-500">{{ $jabatan->slug }}</td>
                            <td class="px-5 py-4">
                                <span class="ui-badge bg-slate-100 text-slate-700">{{ $jabatan->questions_count }}</span>
                            </td>
                            <td class="px-5 py-4">
                                @if ($jabatan->is_active)
                                    <span class="ui-badge bg-emerald-50 text-emerald-700">Aktif</span>
                                @else
                                    <span class="ui-badge bg-slate-100 text-slate-500">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <button wire:click="openEditModal({{ $jabatan->id }})" class="ui-btn-ghost px-3 py-1.5">Edit</button>
                                <button
                                    wire:click="delete({{ $jabatan->id }})"
                                    wire:confirm="Hapus jabatan {{ $jabatan->name }}?"
                                    @disabled($jabatan->questions_count > 0)
                                    @class([
                                        'ui-btn-ghost px-3 py-1.5 text-rose-600 hover:bg-rose-50',
                                        'cursor-not-allowed opacity-50' => $jabatan->questions_count > 0,
                                    ])
                                >
                                    Hapus
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-slate-500">
                                Belum ada jabatan SKB. Tambahkan jabatan untuk mulai mengisi bank soal.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($jabatanSkbs->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $jabatanSkbs->links() }}</div>
        @endif
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeModal"></div>
            <div class="relative max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="sticky top-0 flex items-center justify-between border-b border-slate-100 bg-white px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-900">{{ $editingId ? 'Edit Jabatan' : 'Tambah Jabatan' }}</h2>
                    <button type="button" wire:click="closeModal" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form wire:submit="save" class="space-y-4 p-6">
                    <div>
                        <label class="ui-label">Nama Jabatan</label>
                        <input type="text" wire:model="name" class="ui-input" placeholder="mis. Analis Kebijakan">
                        @error('name') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="ui-label">Deskripsi <span class="font-normal text-slate-400">(opsional)</span></label>
                        <textarea wire:model="description" rows="2" class="ui-input"></textarea>
                        @error('description') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="is_active" class="h-4 w-4 rounded border-slate-300 text-primary-600">
                        Jabatan aktif
                    </label>
                    <p class="text-xs text-slate-500">Slug URL dibuat otomatis dari nama jabatan.</p>
                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="closeModal" class="ui-btn-secondary">Batal</button>
                        <button type="submit" class="ui-btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
```

- [ ] **Step 5: Wire the route**

In `routes/web.php`, add the import next to the other `App\Livewire\Admin\*` imports (alphabetically after `EventsIndex`/before `OnlineParticipants`, i.e. right after the `Formations\Index as FormationsIndex` line):

```php
use App\Livewire\Admin\JabatanSkb\Index as JabatanSkbIndex;
```

Then inside `Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () { ... })`, add this line right after the `/formations` route:

```php
Route::get('/jabatan-skb', JabatanSkbIndex::class)->name('jabatan-skb.index');
```

- [ ] **Step 6: Add the nav item**

In `resources/views/components/admin/sidebar-nav.blade.php`, inside the `'Konten Ujian'` group's `'items'` array, add right after the `admin.questions.index` entry:

```php
['route' => 'admin.jabatan-skb.index', 'label' => 'Soal SKB', 'icon' => 'questions'],
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=JabatanSkbIndexTest`
Expected: PASS (5 tests).

- [ ] **Step 8: Commit** (skipped per Global Constraints)

---

### Task 3: Admin Soal SKB CRUD (per-jabatan question bank)

**Files:**
- Create: `app/Livewire/Admin/JabatanSkb/SoalIndex.php`
- Create: `resources/views/livewire/admin/jabatan-skb/soal-index.blade.php`
- Create: `resources/views/livewire/admin/jabatan-skb/soal-index/table.blade.php`
- Create: `resources/views/livewire/admin/jabatan-skb/soal-index/form-modal.blade.php`
- Create: `resources/views/livewire/admin/jabatan-skb/soal-index/preview-modal.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/livewire/admin/jabatan-skb/index.blade.php` (add "Kelola Soal" link)
- Test: `tests/Feature/Admin/SkbSoalIndexTest.php`

**Interfaces:**
- Consumes: `App\Models\JabatanSkb`, `SkbQuestion`, `SkbQuestionOption` (Task 1); `App\Enums\QuestionOptionContentType`; `App\Services\HtmlSanitizer`; global JS `initQuestionContentEditor(el, wire)` / `saveQuestionForm(wire)` from `resources/js/quill-editor.js` (bind via the exact element id `question-content-editor`).
- Produces: route name `admin.jabatan-skb.soal.index` (GET, `{jabatanSkb}` bound). Task 4/5 post to a route this task does not yet create.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/SkbSoalIndexTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Enums\QuestionOptionContentType;
use App\Enums\UserRole;
use App\Livewire\Admin\JabatanSkb\SoalIndex;
use App\Models\JabatanSkb;
use App\Models\SkbQuestion;
use App\Models\SkbQuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SkbSoalIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_soal_skb_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = $this->createJabatan();

        $this->actingAs($admin)
            ->get(route('admin.jabatan-skb.soal.index', $jabatan))
            ->assertOk()
            ->assertSee($jabatan->name);
    }

    public function test_peserta_cannot_access_soal_skb_page(): void
    {
        $peserta = User::factory()->create(['role' => UserRole::Peserta]);
        $jabatan = $this->createJabatan();

        $this->actingAs($peserta)
            ->get(route('admin.jabatan-skb.soal.index', $jabatan))
            ->assertForbidden();
    }

    public function test_admin_can_create_question_with_text_options(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = $this->createJabatan();

        Livewire::actingAs($admin)
            ->test(SoalIndex::class, ['jabatanSkb' => $jabatan])
            ->set('options', [
                ['label' => 'A', 'content_type' => 'text', 'content' => 'Pilihan A', 'image_path' => null, 'is_correct' => true],
                ['label' => 'B', 'content_type' => 'text', 'content' => 'Pilihan B', 'image_path' => null, 'is_correct' => false],
            ])
            ->set('correctOptionIndex', 0)
            ->call('save', '<p>Soal SKB baru</p>')
            ->assertHasNoErrors();

        $question = SkbQuestion::query()->where('jabatan_skb_id', $jabatan->id)->latest()->first();

        $this->assertNotNull($question);
        $this->assertSame('<p>Soal SKB baru</p>', $question->content);
        $this->assertTrue($question->options->firstWhere('label', 'A')->is_correct);
        $this->assertFalse($question->options->firstWhere('label', 'B')->is_correct);
    }

    public function test_admin_can_create_question_with_image_option(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = $this->createJabatan();

        Livewire::actingAs($admin)
            ->test(SoalIndex::class, ['jabatanSkb' => $jabatan])
            ->set('options', [
                ['label' => 'A', 'content_type' => 'image', 'content' => '', 'image_path' => null, 'is_correct' => true],
                ['label' => 'B', 'content_type' => 'text', 'content' => 'Pilihan B', 'image_path' => null, 'is_correct' => false],
            ])
            ->set('optionImages.0', UploadedFile::fake()->image('option-a.png'))
            ->set('correctOptionIndex', 0)
            ->call('save', '<p>Soal dengan gambar</p>')
            ->assertHasNoErrors();

        $question = SkbQuestion::query()->with('options')->latest()->first();
        $imageOption = $question->options->firstWhere('label', 'A');

        $this->assertSame(QuestionOptionContentType::Image, $imageOption->content_type);
        Storage::disk('public')->assertExists($imageOption->image_path);
    }

    public function test_admin_can_edit_question(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = $this->createJabatan();
        $question = $this->createQuestion($jabatan);

        Livewire::actingAs($admin)
            ->test(SoalIndex::class, ['jabatanSkb' => $jabatan])
            ->call('openEditModal', $question->id)
            ->call('save', '<p>Soal diperbarui</p>')
            ->assertHasNoErrors();

        $this->assertSame('<p>Soal diperbarui</p>', $question->fresh()->content);
    }

    public function test_admin_can_delete_question(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = $this->createJabatan();
        $question = $this->createQuestion($jabatan);

        Livewire::actingAs($admin)
            ->test(SoalIndex::class, ['jabatanSkb' => $jabatan])
            ->call('delete', $question->id);

        $this->assertDatabaseMissing('skb_questions', ['id' => $question->id]);
    }

    private function createJabatan(): JabatanSkb
    {
        return JabatanSkb::query()->create([
            'name' => 'Analis Kebijakan',
            'slug' => 'analis-kebijakan',
            'is_active' => true,
        ]);
    }

    private function createQuestion(JabatanSkb $jabatan): SkbQuestion
    {
        $question = SkbQuestion::query()->create([
            'jabatan_skb_id' => $jabatan->id,
            'content' => '<p>Soal awal</p>',
            'difficulty' => 'medium',
            'is_active' => true,
        ]);

        SkbQuestionOption::query()->create([
            'skb_question_id' => $question->id,
            'label' => 'A',
            'content_type' => QuestionOptionContentType::Text,
            'content' => 'Pilihan A',
            'is_correct' => true,
            'sort_order' => 1,
        ]);

        SkbQuestionOption::query()->create([
            'skb_question_id' => $question->id,
            'label' => 'B',
            'content_type' => QuestionOptionContentType::Text,
            'content' => 'Pilihan B',
            'is_correct' => false,
            'sort_order' => 2,
        ]);

        return $question;
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SkbSoalIndexTest`
Expected: FAIL — route `admin.jabatan-skb.soal.index` and class `SoalIndex` don't exist yet.

- [ ] **Step 3: Create the Livewire component**

`app/Livewire/Admin/JabatanSkb/SoalIndex.php`:

```php
<?php

namespace App\Livewire\Admin\JabatanSkb;

use App\Enums\QuestionOptionContentType;
use App\Models\JabatanSkb;
use App\Models\SkbQuestion;
use App\Models\SkbQuestionOption;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Soal SKB')]
class SoalIndex extends Component
{
    use WithFileUploads, WithPagination;

    public JabatanSkb $jabatanSkb;

    public string $search = '';

    public bool $showModal = false;

    public bool $showPreviewModal = false;

    public ?int $previewQuestionId = null;

    public ?int $editingId = null;

    public string $content = '';

    public string $explanation = '';

    public string $difficulty = 'medium';

    public bool $is_active = true;

    public array $options = [];

    public array $optionImages = [];

    public $editorImage = null;

    public int $correctOptionIndex = 0;

    public function mount(JabatanSkb $jabatanSkb): void
    {
        $this->jabatanSkb = $jabatanSkb;
        $this->resetOptions();
    }

    protected function rules(): array
    {
        return [
            'content' => ['required', 'string'],
            'explanation' => ['nullable', 'string'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'is_active' => ['boolean'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.label' => ['required', 'string', 'max:2'],
            'options.*.content_type' => ['required', 'in:text,image'],
            'options.*.content' => ['nullable', 'string'],
            'options.*.image_path' => ['nullable', 'string', 'regex:/^question-options\/[a-zA-Z0-9_\-\.]+$/'],
            'optionImages.*' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search']);
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openPreviewModal(int $questionId): void
    {
        $this->previewQuestionId = $questionId;
        $this->showPreviewModal = true;
    }

    public function closePreviewModal(): void
    {
        $this->showPreviewModal = false;
        $this->previewQuestionId = null;
    }

    #[Renderless]
    public function processEditorImage(): string
    {
        $this->validate([
            'editorImage' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
        ], [
            'editorImage.required' => 'Gambar wajib dipilih.',
            'editorImage.image' => 'File harus berupa gambar.',
            'editorImage.mimes' => 'Format gambar harus JPG, PNG, WEBP, atau GIF.',
            'editorImage.max' => 'Ukuran gambar maksimal 5 MB.',
        ]);

        $path = $this->editorImage->store('question-content', 'public');
        $this->editorImage = null;

        if (! Storage::disk('public')->exists($path)) {
            throw ValidationException::withMessages([
                'editorImage' => 'File gambar gagal disimpan ke storage.',
            ]);
        }

        return storage_asset($path);
    }

    public function openEditModal(int $questionId): void
    {
        $question = $this->jabatanSkb->questions()->with('options')->findOrFail($questionId);
        $this->editingId = $question->id;
        $this->content = $question->content;
        $this->explanation = $question->explanation ?? '';
        $this->difficulty = $question->difficulty;
        $this->is_active = $question->is_active;
        $this->options = $question->options->sortBy('sort_order')->map(fn ($option) => [
            'label' => $option->label,
            'content_type' => $option->content_type?->value ?? QuestionOptionContentType::Text->value,
            'content' => $option->content ?? '',
            'image_path' => $option->image_path,
            'is_correct' => $option->is_correct,
        ])->values()->toArray();

        $this->optionImages = [];
        $this->correctOptionIndex = max(0, (int) collect($this->options)->search(fn ($option) => $option['is_correct'] ?? false));
        $this->showModal = true;
    }

    public function save(?string $editorContent = null): void
    {
        if ($editorContent !== null) {
            $this->content = $editorContent;
        }

        $validated = $this->validate();
        $this->validateOptionContents();

        $sanitizer = app(HtmlSanitizer::class);

        DB::transaction(function () use ($validated, $sanitizer) {
            $questionData = [
                'jabatan_skb_id' => $this->jabatanSkb->id,
                'content' => $sanitizer->sanitize($validated['content']),
                'explanation' => $sanitizer->sanitize($validated['explanation'] ?: null),
                'difficulty' => $validated['difficulty'],
                'is_active' => $validated['is_active'],
            ];

            $oldImagePaths = [];

            if ($this->editingId) {
                $question = $this->jabatanSkb->questions()->findOrFail($this->editingId);
                $question->update($questionData);

                $oldImagePaths = $question->options()
                    ->whereNotNull('image_path')
                    ->pluck('image_path')
                    ->all();

                SkbQuestionOption::withoutEvents(fn () => $question->options()->delete());
            } else {
                $question = SkbQuestion::query()->create([
                    ...$questionData,
                    'created_by' => auth()->id(),
                ]);
            }

            $savedImagePaths = [];

            foreach ($validated['options'] as $index => $option) {
                $contentType = QuestionOptionContentType::from($option['content_type']);
                $imagePath = null;
                $content = null;

                if ($contentType === QuestionOptionContentType::Image) {
                    $existingImagePath = $option['image_path'] ?? null;

                    if (isset($this->optionImages[$index]) && $this->optionImages[$index]) {
                        $imagePath = $this->optionImages[$index]->store('question-options', 'public');
                    } else {
                        $imagePath = $this->resolveExistingImagePath($existingImagePath);
                    }

                    if ($imagePath) {
                        $savedImagePaths[] = $imagePath;
                    }
                } else {
                    $content = $sanitizer->sanitize($option['content'] ?? '');
                }

                SkbQuestionOption::query()->create([
                    'skb_question_id' => $question->id,
                    'label' => $option['label'],
                    'content_type' => $contentType,
                    'content' => $content,
                    'image_path' => $imagePath,
                    'is_correct' => $index === $this->correctOptionIndex,
                    'sort_order' => $index + 1,
                ]);
            }

            foreach ($oldImagePaths as $oldPath) {
                if (! in_array($oldPath, $savedImagePaths, true)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
        });

        session()->flash('success', 'Soal SKB berhasil disimpan.');
        $this->closeModal();
    }

    public function delete(int $questionId): void
    {
        $this->jabatanSkb->questions()->whereKey($questionId)->delete();
        session()->flash('success', 'Soal SKB berhasil dihapus.');
    }

    public function setOptionType(int $index, string $type): void
    {
        if (! isset($this->options[$index])) {
            return;
        }

        $this->options[$index]['content_type'] = $type;

        if ($type === QuestionOptionContentType::Text->value) {
            unset($this->optionImages[$index]);
            $this->options[$index]['image_path'] = null;
        } else {
            $this->options[$index]['content'] = '';
        }
    }

    public function removeOptionImage(int $index): void
    {
        unset($this->optionImages[$index]);

        if (isset($this->options[$index])) {
            $this->options[$index]['image_path'] = null;
        }
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'content', 'explanation', 'correctOptionIndex', 'optionImages', 'editorImage']);
        $this->difficulty = 'medium';
        $this->is_active = true;
        $this->resetOptions();
        $this->resetValidation();
    }

    private function resetOptions(): void
    {
        $this->options = [
            ['label' => 'A', 'content_type' => 'text', 'content' => '', 'image_path' => null, 'is_correct' => true],
            ['label' => 'B', 'content_type' => 'text', 'content' => '', 'image_path' => null, 'is_correct' => false],
            ['label' => 'C', 'content_type' => 'text', 'content' => '', 'image_path' => null, 'is_correct' => false],
            ['label' => 'D', 'content_type' => 'text', 'content' => '', 'image_path' => null, 'is_correct' => false],
            ['label' => 'E', 'content_type' => 'text', 'content' => '', 'image_path' => null, 'is_correct' => false],
        ];
    }

    private function validateOptionContents(): void
    {
        $errors = [];

        foreach ($this->options as $index => $option) {
            $contentType = $option['content_type'] ?? QuestionOptionContentType::Text->value;

            if ($contentType === QuestionOptionContentType::Text->value) {
                if (trim($option['content'] ?? '') === '') {
                    $errors["options.{$index}.content"] = 'Isi pilihan wajib diisi.';
                }

                continue;
            }

            $hasNewImage = isset($this->optionImages[$index]) && $this->optionImages[$index];
            $hasExistingImage = ! empty($option['image_path']);

            if (! $hasNewImage && ! $hasExistingImage) {
                $errors["optionImages.{$index}"] = 'Gambar pilihan wajib diunggah.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function resolveExistingImagePath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            throw ValidationException::withMessages([
                'options' => 'File gambar yang direferensikan tidak ditemukan.',
            ]);
        }

        return $path;
    }

    public function render()
    {
        $questions = $this->jabatanSkb->questions()
            ->with('options')
            ->when($this->search, fn ($q) => $q->where('content', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);

        $previewQuestion = $this->previewQuestionId
            ? $this->jabatanSkb->questions()->with('options')->find($this->previewQuestionId)
            : null;

        return view('livewire.admin.jabatan-skb.soal-index', compact('questions', 'previewQuestion'));
    }
}
```

- [ ] **Step 4: Create the views**

`resources/views/livewire/admin/jabatan-skb/soal-index.blade.php`:

```blade
<div>
    <x-ui.page-header title="Soal SKB — {{ $jabatanSkb->name }}" description="Bank soal khusus jabatan ini, terpisah dari soal SKD.">
        <a href="{{ route('admin.jabatan-skb.index') }}" wire:navigate class="ui-btn-secondary">Kembali ke Daftar Jabatan</a>
        <button wire:click="openCreateModal" class="ui-btn-primary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Soal
        </button>
    </x-ui.page-header>

    <x-ui.flash-toast />

    @include('livewire.admin.jabatan-skb.soal-index.table')
    @include('livewire.admin.jabatan-skb.soal-index.form-modal')
    @include('livewire.admin.jabatan-skb.soal-index.preview-modal')
</div>

@push('scripts')
    @vite(['resources/js/quill-editor.js'])
@endpush
```

`resources/views/livewire/admin/jabatan-skb/soal-index/table.blade.php`:

```blade
<div class="ui-card mb-5 p-4 sm:p-5">
    <x-ui.filter-toolbar>
        <div class="relative min-w-0 w-full sm:max-w-md sm:flex-1">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari isi soal..." class="ui-input pl-10">
        </div>
    </x-ui.filter-toolbar>
</div>

<div class="ui-table-wrap">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50/80">
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Soal</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kesulitan</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($questions as $question)
                    <tr wire:key="skb-question-{{ $question->id }}" class="transition hover:bg-slate-50/50">
                        <td class="px-5 py-4">
                            <button wire:click="openPreviewModal({{ $question->id }})" class="line-clamp-2 max-w-md text-left text-sm text-slate-800 hover:text-primary-700">
                                {{ Str::limit(strip_tags($question->content), 120) }}
                            </button>
                        </td>
                        <td class="px-5 py-4">
                            <span class="ui-badge bg-slate-100 text-slate-700">{{ ucfirst($question->difficulty) }}</span>
                        </td>
                        <td class="px-5 py-4">
                            @if ($question->is_active)
                                <span class="ui-badge bg-emerald-50 text-emerald-700">Aktif</span>
                            @else
                                <span class="ui-badge bg-slate-100 text-slate-500">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right whitespace-nowrap">
                            <button wire:click="openEditModal({{ $question->id }})" class="ui-btn-ghost px-3 py-1.5">Edit</button>
                            <button
                                wire:click="delete({{ $question->id }})"
                                wire:confirm="Hapus soal ini?"
                                class="ui-btn-ghost px-3 py-1.5 text-rose-600 hover:bg-rose-50"
                            >
                                Hapus
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-12 text-center text-slate-500">
                            Belum ada soal SKB untuk jabatan ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($questions->hasPages())
        <div class="border-t border-slate-100 px-5 py-3">{{ $questions->links() }}</div>
    @endif
</div>
```

`resources/views/livewire/admin/jabatan-skb/soal-index/form-modal.blade.php`:

```blade
@if ($showModal)
    <div wire:key="skb-question-form-{{ $editingId ?? 'new' }}" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeModal"></div>
        <div class="relative w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white/95 px-5 py-3.5 backdrop-blur">
                <h2 class="text-base font-bold text-slate-900">{{ $editingId ? 'Edit Soal' : 'Tambah Soal' }}</h2>
                <button type="button" wire:click="closeModal" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form class="space-y-4 p-5" x-on:submit.prevent>
                <div>
                    <label class="ui-label">Isi Soal</label>
                    <div
                        wire:ignore
                        x-data
                        class="overflow-hidden rounded-xl border border-slate-200 bg-white"
                        x-init="$nextTick(() => initQuestionContentEditor($refs.editor, $wire))"
                    >
                        <div x-ref="editor" id="question-content-editor" class="min-h-[160px]"></div>
                    </div>
                    @error('content') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="ui-label">Pembahasan <span class="font-normal text-slate-400">(opsional)</span></label>
                    <textarea wire:model="explanation" rows="2" class="ui-input"></textarea>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="ui-label">Kesulitan</label>
                        <select wire:model="difficulty" class="ui-select">
                            <option value="easy">Mudah</option>
                            <option value="medium">Sedang</option>
                            <option value="hard">Sulit</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model="is_active" class="h-4 w-4 rounded border-slate-300 text-primary-600">
                            Soal aktif
                        </label>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <h3 class="mb-3 text-sm font-semibold text-slate-800">Pilihan Jawaban</h3>

                    <div class="space-y-2.5">
                        @foreach ($options as $index => $option)
                            @php
                                $contentType = $option['content_type'] ?? 'text';
                                $hasUploadedImage = (isset($optionImages[$index]) && $optionImages[$index]) || ! empty($option['image_path']);
                            @endphp

                            <div wire:key="skb-question-option-{{ $index }}" class="rounded-lg border border-slate-200 bg-white">
                                <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-3 py-2">
                                    <input
                                        type="text"
                                        wire:model="options.{{ $index }}.label"
                                        class="ui-input h-8 w-16 shrink-0 text-center text-xs font-bold"
                                        title="Label opsi"
                                        disabled
                                    >

                                    <div class="inline-flex rounded-md border border-slate-200 bg-slate-50 p-0.5">
                                        <button
                                            type="button"
                                            wire:click="setOptionType({{ $index }}, 'text')"
                                            @class([
                                                'rounded px-2.5 py-1 text-xs font-medium transition',
                                                'bg-white text-primary-700 shadow-sm' => $contentType === 'text',
                                                'text-slate-500 hover:text-slate-700' => $contentType !== 'text',
                                            ])
                                        >Teks</button>
                                        <button
                                            type="button"
                                            wire:click="setOptionType({{ $index }}, 'image')"
                                            @class([
                                                'rounded px-2.5 py-1 text-xs font-medium transition',
                                                'bg-white text-primary-700 shadow-sm' => $contentType === 'image',
                                                'text-slate-500 hover:text-slate-700' => $contentType !== 'image',
                                            ])
                                        >Gambar</button>
                                    </div>

                                    <div class="ml-auto">
                                        <label class="flex cursor-pointer items-center gap-1.5 text-xs font-medium text-slate-600">
                                            <input type="radio" wire:model="correctOptionIndex" value="{{ $index }}" class="text-primary-600">
                                            Benar
                                        </label>
                                    </div>
                                </div>

                                <div class="px-3 py-2.5">
                                    @if ($contentType === 'text')
                                        <input
                                            type="text"
                                            wire:model="options.{{ $index }}.content"
                                            placeholder="Isi pilihan {{ $option['label'] }}"
                                            class="ui-input h-9 w-full text-sm"
                                        >
                                        @error("options.{$index}.content") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    @else
                                        <input type="hidden" wire:model="options.{{ $index }}.image_path">
                                        @if ($hasUploadedImage)
                                            <div class="flex flex-wrap items-center gap-3">
                                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-1.5">
                                                    @if (isset($optionImages[$index]) && $optionImages[$index])
                                                        <img src="{{ $optionImages[$index]->temporaryUrl() }}" alt="Pratinjau {{ $option['label'] }}" class="max-h-28 max-w-[200px] rounded object-contain">
                                                    @else
                                                        <img src="{{ storage_asset($option['image_path']) }}" alt="Pilihan {{ $option['label'] }}" class="max-h-28 max-w-[200px] rounded object-contain">
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <label class="ui-btn-secondary cursor-pointer px-3 py-1.5 text-xs">
                                                        Ganti
                                                        <input type="file" wire:model="optionImages.{{ $index }}" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only">
                                                    </label>
                                                    <button type="button" wire:click="removeOptionImage({{ $index }})" class="ui-btn-ghost px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-50">
                                                        Hapus
                                                    </button>
                                                </div>
                                            </div>
                                            <p wire:loading wire:target="optionImages.{{ $index }}" class="mt-1.5 text-xs text-primary-600">Mengunggah...</p>
                                        @else
                                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-3 transition hover:border-primary-300 hover:bg-primary-50/20">
                                                <svg class="h-7 w-7 shrink-0 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <span class="min-w-0">
                                                    <span class="block text-xs font-medium text-slate-700">Unggah gambar pilihan {{ $option['label'] }}</span>
                                                    <span class="text-[11px] text-slate-400">JPG, PNG, WEBP, GIF · maks. 5 MB</span>
                                                </span>
                                                <input type="file" wire:model="optionImages.{{ $index }}" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only">
                                            </label>
                                            <p wire:loading wire:target="optionImages.{{ $index }}" class="mt-1.5 text-xs text-primary-600">Mengunggah...</p>
                                        @endif
                                        @error("optionImages.{$index}") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                    <button type="button" wire:click="closeModal" class="ui-btn-secondary">Batal</button>
                    <button
                        type="button"
                        class="ui-btn-primary"
                        wire:loading.attr="disabled"
                        wire:target="save, optionImages"
                        x-on:click="saveQuestionForm($wire)"
                    >Simpan Soal</button>
                </div>
            </form>
        </div>
    </div>
@endif
```

`resources/views/livewire/admin/jabatan-skb/soal-index/preview-modal.blade.php`:

```blade
@if ($showPreviewModal && $previewQuestion)
    <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closePreviewModal"></div>
        <div class="relative w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white/95 px-5 py-3.5 backdrop-blur">
                <h2 class="text-base font-bold text-slate-900">Pratinjau Soal</h2>
                <button type="button" wire:click="closePreviewModal" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="space-y-4 p-5">
                <div class="prose prose-sm max-w-none">{!! $previewQuestion->content !!}</div>
                <div class="space-y-2">
                    @foreach ($previewQuestion->options as $option)
                        <div @class([
                            'flex items-center gap-2 rounded-lg border px-3 py-2 text-sm',
                            'border-emerald-300 bg-emerald-50' => $option->is_correct,
                            'border-slate-200' => ! $option->is_correct,
                        ])>
                            <span class="font-bold">{{ $option->label }}.</span>
                            @if ($option->content_type === \App\Enums\QuestionOptionContentType::Image)
                                <img src="{{ storage_asset($option->image_path) }}" alt="Pilihan {{ $option->label }}" class="max-h-20 rounded">
                            @else
                                <span>{{ $option->content }}</span>
                            @endif
                            @if ($option->is_correct)
                                <span class="ml-auto ui-badge bg-emerald-100 text-emerald-700">Benar</span>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if ($previewQuestion->explanation)
                    <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                        <p class="mb-1 font-semibold text-slate-700">Pembahasan</p>
                        {!! $previewQuestion->explanation !!}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
```

- [ ] **Step 5: Wire the route**

In `routes/web.php`, add the import next to the `JabatanSkbIndex` import added in Task 2:

```php
use App\Livewire\Admin\JabatanSkb\SoalIndex as JabatanSkbSoalIndex;
```

Then add this line right after the `/jabatan-skb` route added in Task 2:

```php
Route::get('/jabatan-skb/{jabatanSkb}/soal', JabatanSkbSoalIndex::class)->name('jabatan-skb.soal.index');
```

- [ ] **Step 6: Add the "Kelola Soal" link**

In `resources/views/livewire/admin/jabatan-skb/index.blade.php`, in the row actions cell, add this link right before the "Edit" button:

```blade
<a href="{{ route('admin.jabatan-skb.soal.index', $jabatan) }}" wire:navigate class="ui-btn-ghost px-3 py-1.5">Kelola Soal</a>
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=SkbSoalIndexTest`
Expected: PASS (6 tests).

- [ ] **Step 8: Commit** (skipped per Global Constraints)

---

### Task 4: SKB question import backend (service + controller, no UI yet)

**Files:**
- Create: `app/Imports/Concerns/ValidatesSkbQuestionImportRows.php`
- Create: `app/Imports/SkbQuestionsSheetImport.php`
- Create: `app/Imports/SkbQuestionsImport.php`
- Create: `app/Imports/SkbQuestionsQueuedImport.php`
- Create: `app/Imports/SkbQuestionsImportValidator.php`
- Create: `app/Imports/SkbQuestionsRowCounter.php`
- Create: `app/Services/SkbQuestionImportService.php`
- Create: `app/Http/Controllers/Admin/SkbQuestionImportController.php`
- Create: `app/Exports/SkbQuestionsImportTemplate.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/SkbQuestionImportTest.php`

**Interfaces:**
- Consumes: `App\Models\SkbQuestion`, `SkbQuestionOption`, `SkbQuestionImportJob` (Task 1); `App\Enums\QuestionOptionContentType`, `App\Enums\QuestionImportStatus`; `App\Services\HtmlSanitizer`; `App\Support\ImportErrorReport`; `App\Exceptions\ImportFailedException`; `App\Imports\Concerns\DeletesStoredImportFile` (reused as-is).
- Produces: `App\Services\SkbQuestionImportService::import(string $storedPath, int $jabatanSkbId, int $createdBy): array{queued: bool, message: string, count: int, import_job_id?: int}`; route name `admin.jabatan-skb.soal.import` (POST, `{jabatanSkb}`) and `admin.jabatan-skb.soal.import-template` (GET, `{jabatanSkb}`). Task 5 wires these into the `SoalIndex` UI — this task only needs to work over plain HTTP.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/SkbQuestionImportTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\JabatanSkb;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SkbQuestionImportTest extends TestCase
{
    use RefreshDatabase;

    private const CSV_HEADER = 'content,explanation,difficulty,option_a,option_b,option_c,option_d,option_e,correct_option';

    public function test_admin_can_import_skb_questions_from_csv(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Analis Kebijakan', 'slug' => 'analis-kebijakan', 'is_active' => true]);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'Soal SKB satu,,medium,Pilihan A,Pilihan B,Pilihan C,Pilihan D,Pilihan E,a',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal-skb.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.jabatan-skb.soal.import', $jabatan), ['file' => $file]);

        $response->assertRedirect(route('admin.jabatan-skb.soal.index', $jabatan));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('skb_questions', [
            'jabatan_skb_id' => $jabatan->id,
        ]);

        $this->assertDatabaseHas('skb_question_options', [
            'label' => 'A',
            'is_correct' => true,
        ]);
    }

    public function test_large_import_is_queued_and_processes_via_sync_queue(): void
    {
        // phpunit.xml sets QUEUE_CONNECTION=sync, so a queued import job
        // runs immediately in-process during the test — no fake needed.
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Perawat', 'slug' => 'perawat', 'is_active' => true]);

        $rows = [self::CSV_HEADER];

        for ($i = 1; $i <= 101; $i++) {
            $rows[] = "Soal SKB nomor {$i},,medium,Pilihan A,Pilihan B,Pilihan C,Pilihan D,Pilihan E,a";
        }

        $file = UploadedFile::fake()->createWithContent('soal-skb-besar.csv', implode("\n", $rows));

        $response = $this->actingAs($admin)
            ->post(route('admin.jabatan-skb.soal.import', $jabatan), ['file' => $file]);

        $response->assertRedirect(route('admin.jabatan-skb.soal.index', $jabatan));
        $response->assertSessionHas('info');

        $this->assertDatabaseHas('skb_question_import_jobs', [
            'jabatan_skb_id' => $jabatan->id,
            'total_rows' => 101,
            'status' => 'completed',
        ]);

        $this->assertSame(101, $jabatan->questions()->count());
    }

    public function test_import_requires_all_option_columns(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Auditor', 'slug' => 'auditor', 'is_active' => true]);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'Soal SKB kurang opsi,,medium,Pilihan A,Pilihan B,Pilihan C,Pilihan D,,a',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal-skb.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.jabatan-skb.soal.import', $jabatan), ['file' => $file]);

        $response->assertRedirect(route('admin.jabatan-skb.soal.index', $jabatan));
        $response->assertSessionHas('import_errors');

        $this->assertDatabaseMissing('skb_questions', ['jabatan_skb_id' => $jabatan->id]);
    }

    public function test_import_requires_correct_option(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Dokter', 'slug' => 'dokter', 'is_active' => true]);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'Soal SKB tanpa jawaban,,medium,Pilihan A,Pilihan B,Pilihan C,Pilihan D,Pilihan E,',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal-skb.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.jabatan-skb.soal.import', $jabatan), ['file' => $file]);

        $response->assertRedirect(route('admin.jabatan-skb.soal.index', $jabatan));
        $response->assertSessionHas('import_errors');

        $this->assertDatabaseMissing('skb_questions', ['jabatan_skb_id' => $jabatan->id]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SkbQuestionImportTest`
Expected: FAIL — route `admin.jabatan-skb.soal.import` doesn't exist yet.

- [ ] **Step 3: Create the validation trait**

`app/Imports/Concerns/ValidatesSkbQuestionImportRows.php`:

```php
<?php

namespace App\Imports\Concerns;

use Illuminate\Support\Collection;

trait ValidatesSkbQuestionImportRows
{
    protected function filterSkbQuestionRows(Collection $rows): Collection
    {
        return $rows->filter(fn ($row) => $this->skbQuestionRowHasContent($row))->values();
    }

    protected function skbQuestionRowHasContent(mixed $row): bool
    {
        $values = $row instanceof Collection ? $row->toArray() : (array) $row;

        foreach ($values as $value) {
            if (filled($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{row: ?int, column: ?string, value: ?string, message: string}>
     */
    protected function collectSkbQuestionBusinessRuleErrors(Collection $rows, int $rowOffset = 0): array
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $rowOffset + $index + 2;

            foreach (['a', 'b', 'c', 'd', 'e'] as $label) {
                $optionKey = "option_{$label}";

                if ($this->skbImportCellIsBlank($row[$optionKey] ?? null)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'column' => 'Opsi '.strtoupper($label),
                        'value' => $row[$optionKey] ?? null,
                        'message' => 'Pilihan jawaban wajib diisi.',
                    ];
                }
            }

            $correctOptionRaw = $row['correct_option'] ?? null;

            if ($this->skbImportCellIsBlank($correctOptionRaw)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => 'Jawaban Benar',
                    'value' => $correctOptionRaw,
                    'message' => 'Jawaban benar wajib diisi.',
                ];

                continue;
            }

            $correctOption = strtoupper(trim((string) $correctOptionRaw));

            if (! in_array($correctOption, ['A', 'B', 'C', 'D', 'E'], true)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => 'Jawaban Benar',
                    'value' => $correctOptionRaw,
                    'message' => 'Jawaban benar harus A, B, C, D, atau E.',
                ];
            }
        }

        return $errors;
    }

    protected function skbImportCellIsBlank(mixed $value): bool
    {
        return trim((string) ($value ?? '')) === '';
    }
}
```

- [ ] **Step 4: Create the import classes**

`app/Imports/SkbQuestionsSheetImport.php`:

```php
<?php

namespace App\Imports;

use App\Enums\QuestionOptionContentType;
use App\Imports\Concerns\ValidatesSkbQuestionImportRows;
use App\Models\SkbQuestion;
use App\Models\SkbQuestionImportJob;
use App\Models\SkbQuestionOption;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SkbQuestionsSheetImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    use ValidatesSkbQuestionImportRows;

    public function __construct(
        private readonly int $jabatanSkbId,
        private readonly ?int $createdBy = null,
        private readonly ?int $importJobId = null,
    ) {}

    public function chunkSize(): int
    {
        return 100;
    }

    public function collection(Collection $rows): void
    {
        $rows = $this->filterSkbQuestionRows($rows);

        if ($rows->isEmpty()) {
            return;
        }

        $sanitizer = app(HtmlSanitizer::class);

        $importedCount = $rows->count();

        DB::transaction(function () use ($rows, $sanitizer) {
            foreach ($rows as $row) {
                $question = SkbQuestion::query()->create([
                    'jabatan_skb_id' => $this->jabatanSkbId,
                    'content' => $sanitizer->sanitize($row['content']),
                    'explanation' => $sanitizer->sanitize($row['explanation'] ?? null),
                    'difficulty' => $row['difficulty'] ?? 'medium',
                    'is_active' => true,
                    'created_by' => $this->createdBy,
                ]);

                $this->createOptions($question, $row, $sanitizer);
            }
        });

        if ($this->importJobId) {
            SkbQuestionImportJob::query()->find($this->importJobId)?->advance($importedCount);
        }
    }

    private function createOptions(SkbQuestion $question, Collection|array $row, HtmlSanitizer $sanitizer): void
    {
        foreach (['a', 'b', 'c', 'd', 'e'] as $index => $label) {
            $contentKey = "option_{$label}";

            if (empty($row[$contentKey])) {
                continue;
            }

            SkbQuestionOption::query()->create([
                'skb_question_id' => $question->id,
                'label' => strtoupper($label),
                'content_type' => QuestionOptionContentType::Text,
                'content' => $sanitizer->sanitize($row[$contentKey]),
                'is_correct' => strtoupper($row['correct_option'] ?? '') === strtoupper($label),
                'sort_order' => $index + 1,
            ]);
        }
    }
}
```

`app/Imports/SkbQuestionsImport.php` (sync wrapper — reuses `DeletesStoredImportFile` as-is):

```php
<?php

namespace App\Imports;

use App\Imports\Concerns\DeletesStoredImportFile;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SkbQuestionsImport implements WithChunkReading, WithEvents, WithMultipleSheets
{
    use DeletesStoredImportFile;

    public function __construct(
        private readonly int $jabatanSkbId,
        private readonly ?int $createdBy = null,
        private readonly ?string $storedPath = null,
    ) {}

    public function chunkSize(): int
    {
        return 100;
    }

    public function sheets(): array
    {
        return [
            'Template Soal SKB' => new SkbQuestionsSheetImport($this->jabatanSkbId, $this->createdBy),
        ];
    }
}
```

`app/Imports/SkbQuestionsQueuedImport.php`:

```php
<?php

namespace App\Imports;

use App\Models\SkbQuestionImportJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;

class SkbQuestionsQueuedImport implements ShouldQueue, WithChunkReading, WithEvents, WithMultipleSheets
{
    use Importable;

    public function __construct(
        private readonly int $jabatanSkbId,
        private readonly ?int $createdBy = null,
        private readonly ?string $storedPath = null,
        private readonly ?int $importJobId = null,
    ) {}

    public function chunkSize(): int
    {
        return 100;
    }

    public function sheets(): array
    {
        return [
            'Template Soal SKB' => new SkbQuestionsSheetImport($this->jabatanSkbId, $this->createdBy, $this->importJobId),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                if ($this->storedPath) {
                    Storage::disk('local')->delete($this->storedPath);
                }

                if ($this->importJobId) {
                    SkbQuestionImportJob::query()->find($this->importJobId)?->markCompleted();
                }
            },
            ImportFailed::class => function (ImportFailed $event) {
                if ($this->importJobId) {
                    SkbQuestionImportJob::query()
                        ->find($this->importJobId)
                        ?->markFailed($event->getException()->getMessage());
                }
            },
        ];
    }
}
```

`app/Imports/SkbQuestionsImportValidator.php` (two classes in one file, mirroring `QuestionsImportValidator.php`):

```php
<?php

namespace App\Imports;

use App\Exceptions\ImportFailedException;
use App\Imports\Concerns\ValidatesSkbQuestionImportRows;
use App\Support\ImportErrorReport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\AfterImport;

class SkbQuestionsImportValidator implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Template Soal SKB' => new SkbQuestionsSheetImportValidator,
        ];
    }
}

class SkbQuestionsSheetImportValidator implements ToCollection, WithChunkReading, WithEvents, WithHeadingRow, WithValidation
{
    use ValidatesSkbQuestionImportRows;

    private int $rowOffset = 0;

    /** @var array<int, array{row: ?int, column: ?string, value: ?string, message: string}> */
    private array $errors = [];

    public function chunkSize(): int
    {
        return 200;
    }

    public function collection(Collection $rows): void
    {
        $rows = $this->filterSkbQuestionRows($rows);

        if ($rows->isEmpty()) {
            return;
        }

        $this->errors = array_merge(
            $this->errors,
            $this->collectSkbQuestionBusinessRuleErrors($rows, $this->rowOffset),
        );

        $this->rowOffset += $rows->count();
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                if ($this->rowOffset === 0) {
                    throw new ImportFailedException(new ImportErrorReport('Import Soal SKB Gagal', [[
                        'row' => null,
                        'column' => null,
                        'value' => null,
                        'message' => 'Tidak ada baris data pada sheet Template Soal SKB. Pastikan file berisi header dan minimal 1 baris soal.',
                    ]]));
                }

                if ($this->errors !== []) {
                    throw new ImportFailedException(new ImportErrorReport('Import Soal SKB Gagal', $this->errors));
                }
            },
        ];
    }

    public function rules(): array
    {
        return [
            '*.content' => ['required', 'string'],
        ];
    }
}
```

`app/Imports/SkbQuestionsRowCounter.php`:

```php
<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SkbQuestionsRowCounter implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Template Soal SKB' => new SkbQuestionsSheetRowCounter,
        ];
    }
}

class SkbQuestionsSheetRowCounter implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        //
    }
}
```

- [ ] **Step 5: Create the import service**

`app/Services/SkbQuestionImportService.php`:

```php
<?php

namespace App\Services;

use App\Enums\QuestionImportStatus;
use App\Exceptions\ImportFailedException;
use App\Imports\Concerns\ValidatesSkbQuestionImportRows;
use App\Imports\SkbQuestionsImport;
use App\Imports\SkbQuestionsImportValidator;
use App\Imports\SkbQuestionsQueuedImport;
use App\Imports\SkbQuestionsRowCounter;
use App\Models\SkbQuestionImportJob;
use App\Support\ImportErrorReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class SkbQuestionImportService
{
    use ValidatesSkbQuestionImportRows;

    public const BACKGROUND_ROW_THRESHOLD = 100;

    /**
     * @return array{queued: bool, message: string, count: int, import_job_id?: int}
     */
    public function import(string $storedPath, int $jabatanSkbId, int $createdBy): array
    {
        $rowCount = $this->countRows($storedPath);

        if ($rowCount === 0) {
            throw new ImportFailedException(new ImportErrorReport('Import Soal SKB Gagal', [[
                'row' => null,
                'column' => null,
                'value' => null,
                'message' => 'Tidak ada baris data pada sheet Template Soal SKB. Pastikan file berisi header dan minimal 1 baris soal.',
            ]]));
        }

        $this->validateFile($storedPath);

        Excel::clearResolvedInstance();

        if ($rowCount > self::BACKGROUND_ROW_THRESHOLD) {
            $importJob = SkbQuestionImportJob::query()->create([
                'jabatan_skb_id' => $jabatanSkbId,
                'user_id' => $createdBy,
                'total_rows' => $rowCount,
                'status' => QuestionImportStatus::Pending,
            ]);

            Excel::queueImport(
                new SkbQuestionsQueuedImport($jabatanSkbId, $createdBy, $storedPath, $importJob->id),
                $storedPath,
                'local',
            );

            return [
                'queued' => true,
                'count' => $rowCount,
                'import_job_id' => $importJob->id,
                'message' => "Import {$rowCount} soal sedang diproses di background. Progress dapat dipantau di halaman ini.",
            ];
        }

        Excel::import(
            new SkbQuestionsImport($jabatanSkbId, $createdBy, $storedPath),
            Storage::disk('local')->path($storedPath),
        );

        return [
            'queued' => false,
            'count' => $rowCount,
            'message' => "{$rowCount} soal berhasil diimpor.",
        ];
    }

    public function countRows(string $storedPath): int
    {
        return $this->filterSkbQuestionRows($this->readTemplateRows($storedPath))->count();
    }

    private function validateFile(string $storedPath): void
    {
        try {
            Excel::import(
                new SkbQuestionsImportValidator,
                Storage::disk('local')->path($storedPath),
            );
        } catch (ExcelValidationException $exception) {
            throw new ImportFailedException(
                ImportErrorReport::fromExcelValidation($exception, 'Import Soal SKB Gagal'),
            );
        }
    }

    private function readTemplateRows(string $storedPath): Collection
    {
        $sheets = Excel::toCollection(new SkbQuestionsRowCounter, $storedPath, 'local');

        return $sheets->get('Template Soal SKB', collect());
    }
}
```

- [ ] **Step 6: Create the controller and template export**

`app/Http/Controllers/Admin/SkbQuestionImportController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JabatanSkb;
use App\Services\SkbQuestionImportService;
use App\Support\ImportErrorReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class SkbQuestionImportController extends Controller
{
    public function store(Request $request, SkbQuestionImportService $importService, JabatanSkb $jabatanSkb): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:51200'],
        ], [
            'file.required' => 'File Excel wajib dipilih.',
            'file.file' => 'Unggahan harus berupa file.',
            'file.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 50 MB.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('admin.jabatan-skb.soal.index', $jabatanSkb)
                ->with('import_errors', ImportErrorReport::fromValidationException(
                    new ValidationException($validator),
                    'Import Soal SKB Gagal',
                )->toSession())
                ->with('error', 'Import Soal SKB Gagal');
        }

        $storedPath = $request->file('file')->store('imports/skb-questions', 'local');

        try {
            $result = $importService->import($storedPath, $jabatanSkb->id, (int) auth()->id());

            if (! $result['queued']) {
                Storage::disk('local')->delete($storedPath);
            }

            return redirect()
                ->route('admin.jabatan-skb.soal.index', $jabatanSkb)
                ->with($result['queued'] ? 'info' : 'success', $result['message']);
        } catch (Throwable $throwable) {
            Storage::disk('local')->delete($storedPath);

            $report = ImportErrorReport::fromThrowable($throwable, 'Import Soal SKB Gagal');

            return redirect()
                ->route('admin.jabatan-skb.soal.index', $jabatanSkb)
                ->with('import_errors', $report->toSession())
                ->with('error', 'Import Soal SKB Gagal');
        }
    }
}
```

`app/Exports/SkbQuestionsImportTemplate.php`:

```php
<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SkbQuestionsImportTemplate implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Template Soal SKB';
    }

    public function headings(): array
    {
        return [
            'content',
            'explanation',
            'difficulty',
            'option_a',
            'option_b',
            'option_c',
            'option_d',
            'option_e',
            'correct_option',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Contoh soal SKB: Apa tugas utama jabatan ini dalam pelayanan publik?',
                'Pembahasan opsional',
                'medium',
                'Pilihan A',
                'Pilihan B',
                'Pilihan C',
                'Pilihan D',
                'Pilihan E',
                'a',
            ],
        ];
    }
}
```

- [ ] **Step 7: Wire the routes**

In `routes/web.php`, add these imports next to the `JabatanSkbSoalIndex` import added in Task 3:

```php
use App\Exports\SkbQuestionsImportTemplate;
use App\Http\Controllers\Admin\SkbQuestionImportController;
```

Then add these two lines right after the `jabatan-skb.soal.index` route added in Task 3:

```php
Route::post('/jabatan-skb/{jabatanSkb}/soal/import', [SkbQuestionImportController::class, 'store'])->name('jabatan-skb.soal.import');
Route::get('/jabatan-skb/{jabatanSkb}/soal/import-template', function () {
    return Excel::download(new SkbQuestionsImportTemplate, 'template-import-soal-skb.xlsx');
})->name('jabatan-skb.soal.import-template');
```

(`Excel` is already imported at the top of `routes/web.php` — no new import needed for it.)

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --filter=SkbQuestionImportTest`
Expected: PASS (4 tests).

- [ ] **Step 9: Commit** (skipped per Global Constraints)

---

### Task 5: Wire import UI into Soal SKB page

**Files:**
- Modify: `app/Livewire/Admin/JabatanSkb/SoalIndex.php`
- Modify: `resources/views/livewire/admin/jabatan-skb/soal-index.blade.php`
- Create: `resources/views/livewire/admin/jabatan-skb/soal-index/import-modal.blade.php`
- Create: `resources/views/livewire/admin/jabatan-skb/soal-index/import-progress.blade.php`
- Test: `tests/Feature/Admin/SkbSoalImportUiTest.php`

**Interfaces:**
- Consumes: `App\Livewire\Concerns\HandlesImportErrorModal` (reused as-is — declares `$showImportErrorModal`/`$importErrorReport`, expects the host component to declare `public bool $showImportModal`); `App\Models\SkbQuestionImportJob`; `App\Enums\QuestionImportStatus`; the `x-admin.import-excel-modal` and `x-ui.import-error-modal` Blade components (reused as-is, both read the property name `showImportModal` directly — do not rename it).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/SkbSoalImportUiTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\JabatanSkb;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SkbSoalImportUiTest extends TestCase
{
    use RefreshDatabase;

    private const CSV_HEADER = 'content,explanation,difficulty,option_a,option_b,option_c,option_d,option_e,correct_option';

    public function test_soal_skb_page_shows_import_button(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Analis Kebijakan', 'slug' => 'analis-kebijakan', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.jabatan-skb.soal.index', $jabatan))
            ->assertOk()
            ->assertSee('Import Soal');
    }

    public function test_failed_import_shows_error_modal_in_response(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Auditor', 'slug' => 'auditor', 'is_active' => true]);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'Soal SKB kurang opsi,,medium,Pilihan A,Pilihan B,Pilihan C,Pilihan D,,a',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal-skb.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.jabatan-skb.soal.import', $jabatan), ['file' => $file]);

        $response->assertRedirect(route('admin.jabatan-skb.soal.index', $jabatan));
        $response->assertSessionHas('import_errors');

        $followUp = $this->actingAs($admin)->get(route('admin.jabatan-skb.soal.index', $jabatan));

        $followUp->assertOk();
        $followUp->assertSee('Import Soal SKB Gagal', false);
        $followUp->assertSee('Opsi E', false);
        $followUp->assertSee('Pilihan jawaban wajib diisi.', false);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SkbSoalImportUiTest`
Expected: FAIL — no "Import Soal" button yet, and `import_errors` isn't rendered on the page yet.

- [ ] **Step 3: Wire the trait and import-job lookup into the component**

In `app/Livewire/Admin/JabatanSkb/SoalIndex.php`:

Change the `use` statements at the top to add:

```php
use App\Enums\QuestionImportStatus;
use App\Livewire\Concerns\HandlesImportErrorModal;
use App\Models\SkbQuestionImportJob;
```

Change the class's trait line from:

```php
    use WithFileUploads, WithPagination;
```

to:

```php
    use HandlesImportErrorModal, WithFileUploads, WithPagination;
```

Add this property next to the other `public bool` properties:

```php
    public bool $showImportModal = false;

    public ?int $dismissedImportJobId = null;
```

Add a `mount()` method (this component didn't have one before Task 5 — Task 3's `mount()` only set `$this->jabatanSkb` and called `resetOptions()`; extend it):

```php
    public function mount(JabatanSkb $jabatanSkb): void
    {
        $this->jabatanSkb = $jabatanSkb;
        $this->resetOptions();
        $this->mountImportErrorModal();
    }
```

Add these two methods next to `closePreviewModal()`:

```php
    public function refreshImportProgress(): void
    {
        // Livewire re-render akan memuat ulang status import terbaru.
    }

    public function dismissImportProgress(int $importJobId): void
    {
        $this->dismissedImportJobId = $importJobId;
    }
```

Add this private method next to `resolveExistingImagePath()`:

```php
    private function activeImportJob(): ?SkbQuestionImportJob
    {
        return SkbQuestionImportJob::query()
            ->where('jabatan_skb_id', $this->jabatanSkb->id)
            ->where('user_id', auth()->id())
            ->when($this->dismissedImportJobId, fn ($query) => $query->where('id', '!=', $this->dismissedImportJobId))
            ->where(function ($query) {
                $query->whereIn('status', [
                    QuestionImportStatus::Pending,
                    QuestionImportStatus::Processing,
                ])->orWhere(function ($query) {
                    $query->where('status', QuestionImportStatus::Completed)
                        ->where('completed_at', '>=', now()->subHour());
                })->orWhere(function ($query) {
                    $query->where('status', QuestionImportStatus::Failed)
                        ->where('updated_at', '>=', now()->subHour());
                });
            })
            ->latest()
            ->first();
    }
```

Update `render()` to include the active import job:

```php
    public function render()
    {
        $questions = $this->jabatanSkb->questions()
            ->with('options')
            ->when($this->search, fn ($q) => $q->where('content', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);

        $previewQuestion = $this->previewQuestionId
            ? $this->jabatanSkb->questions()->with('options')->find($this->previewQuestionId)
            : null;

        $importJob = $this->activeImportJob();

        return view('livewire.admin.jabatan-skb.soal-index', compact('questions', 'previewQuestion', 'importJob'));
    }
```

- [ ] **Step 4: Create the import UI partials**

`resources/views/livewire/admin/jabatan-skb/soal-index/import-modal.blade.php`:

```blade
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
```

`resources/views/livewire/admin/jabatan-skb/soal-index/import-progress.blade.php` (identical structure to the existing `livewire/admin/questions/index/import-progress.blade.php` — same enum, same job field names, so it's a straight copy):

```blade
@if ($importJob)
    @php
        $isActive = $importJob->status->isActive();
        $percent = $importJob->progressPercent();
        $statusLabel = $importJob->status->label();
    @endphp

    <div
        class="ui-card mb-5 overflow-hidden"
        @if ($isActive) wire:poll.2s="refreshImportProgress" @endif
    >
        <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-semibold text-slate-800">Import Soal SKB Background</h3>
                        <span @class([
                            'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                            'bg-amber-100 text-amber-800' => $importJob->status === \App\Enums\QuestionImportStatus::Pending,
                            'bg-blue-100 text-blue-800' => $importJob->status === \App\Enums\QuestionImportStatus::Processing,
                            'bg-emerald-100 text-emerald-800' => $importJob->status === \App\Enums\QuestionImportStatus::Completed,
                            'bg-red-100 text-red-800' => $importJob->status === \App\Enums\QuestionImportStatus::Failed,
                        ])>
                            {{ $statusLabel }}
                        </span>
                    </div>

                    @if ($importJob->status === \App\Enums\QuestionImportStatus::Pending)
                        <p class="mt-1 text-sm text-slate-600">
                            Menunggu queue worker memproses {{ number_format($importJob->total_rows) }} soal.
                        </p>
                        @if ($importJob->isStale())
                            <p class="mt-1 text-sm font-medium text-amber-700">
                                Proses belum dimulai. Pastikan queue worker berjalan:
                                <code class="rounded bg-amber-50 px-1.5 py-0.5 text-xs">php artisan queue:work</code>
                            </p>
                        @endif
                    @elseif ($importJob->status === \App\Enums\QuestionImportStatus::Processing)
                        <p class="mt-1 text-sm text-slate-600">
                            Mengimpor {{ number_format($importJob->processed_rows) }} dari {{ number_format($importJob->total_rows) }} soal...
                        </p>
                    @elseif ($importJob->status === \App\Enums\QuestionImportStatus::Completed)
                        <p class="mt-1 text-sm text-emerald-700">
                            {{ number_format($importJob->total_rows) }} soal berhasil diimpor. Daftar soal telah diperbarui.
                        </p>
                    @else
                        <p class="mt-1 text-sm text-red-700">
                            {{ $importJob->error_message ?: 'Import gagal diproses.' }}
                        </p>
                    @endif
                </div>

                @unless ($isActive)
                    <button
                        type="button"
                        wire:click="dismissImportProgress({{ $importJob->id }})"
                        class="shrink-0 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Tutup notifikasi import"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endunless
            </div>
        </div>

        <div class="px-4 py-4 sm:px-5">
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="font-medium text-slate-700">Progress</span>
                <span @class([
                    'font-bold',
                    'text-primary-600' => $isActive,
                    'text-emerald-600' => $importJob->status === \App\Enums\QuestionImportStatus::Completed,
                    'text-red-600' => $importJob->status === \App\Enums\QuestionImportStatus::Failed,
                    'text-amber-600' => $importJob->status === \App\Enums\QuestionImportStatus::Pending,
                ])>
                    {{ $percent }}%
                </span>
            </div>
            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                <div
                    @class([
                        'h-full rounded-full transition-all duration-500 ease-out',
                        'bg-gradient-to-r from-primary-500 to-indigo-500' => $isActive,
                        'bg-emerald-500' => $importJob->status === \App\Enums\QuestionImportStatus::Completed,
                        'bg-red-500' => $importJob->status === \App\Enums\QuestionImportStatus::Failed,
                        'bg-amber-400' => $importJob->status === \App\Enums\QuestionImportStatus::Pending,
                    ])
                    style="width: {{ $percent }}%"
                ></div>
            </div>

            @if ($isActive)
                <p class="mt-2 flex items-center gap-1.5 text-xs text-slate-500">
                    <svg class="h-3.5 w-3.5 animate-spin text-primary-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Memperbarui otomatis setiap 2 detik
                </p>
            @endif
        </div>
    </div>
@endif
```

- [ ] **Step 5: Wire the partials and the "Import Soal" button into the page**

Replace `resources/views/livewire/admin/jabatan-skb/soal-index.blade.php` (created in Task 3) with:

```blade
<div>
    <x-ui.page-header title="Soal SKB — {{ $jabatanSkb->name }}" description="Bank soal khusus jabatan ini, terpisah dari soal SKD.">
        <a href="{{ route('admin.jabatan-skb.index') }}" wire:navigate class="ui-btn-secondary">Kembali ke Daftar Jabatan</a>
        <button wire:click="$set('showImportModal', true)" class="ui-btn-secondary">Import Soal</button>
        <button wire:click="openCreateModal" class="ui-btn-primary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Soal
        </button>
    </x-ui.page-header>

    <x-ui.flash-toast />

    @include('livewire.admin.jabatan-skb.soal-index.import-progress')
    @include('livewire.admin.jabatan-skb.soal-index.table')
    @include('livewire.admin.jabatan-skb.soal-index.form-modal')
    @include('livewire.admin.jabatan-skb.soal-index.preview-modal')
    @include('livewire.admin.jabatan-skb.soal-index.import-modal')
    <x-ui.import-error-modal :show="$showImportErrorModal" :report="$importErrorReport" />
</div>

@push('scripts')
    @vite(['resources/js/quill-editor.js'])
@endpush
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=SkbSoalImportUiTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Run the full new test suite together**

Run: `php artisan test --filter="SkbQuestionTest|JabatanSkbIndexTest|SkbSoalIndexTest|SkbQuestionImportTest|SkbSoalImportUiTest"`
Expected: PASS (all 20 tests across the 5 new test files).

- [ ] **Step 8: Commit** (skipped per Global Constraints)

---

## Manual verification (localhost)

After Task 5, verify in the browser (server already running per this session — `http://127.0.0.1:8000`):

1. Log in as admin (`admin@simulasicbt.test` / `password`).
2. Sidebar → "Konten Ujian" → "Soal SKB" → create a jabatan (e.g. "Analis Kebijakan").
3. Click "Kelola Soal" → "Tambah Soal" → fill content, options, mark one correct → Save. Confirm it appears in the table and preview modal shows the correct badge.
4. Click "Import Soal" → download the template → confirm the sheet name is "Template Soal SKB" with columns `content, explanation, difficulty, option_a..e, correct_option` → fill a row → upload → confirm the new question appears.
5. Go back to "Soal SKB" jabatan list → confirm the question count updated and deleting the jabatan is blocked while it has questions.
