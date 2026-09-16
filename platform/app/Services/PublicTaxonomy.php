<?php

namespace App\Services;

use App\Models\{Media, TaxonomyTerm};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PublicTaxonomy
{
    private const HOME_SECTIONS = ['videos', 'beitraege', 'buecher'];

    public function __construct(private PublicContent $content, private PublicBooks $books) {}

    public function resolve(string|int|null $value): ?TaxonomyTerm
    {
        if ($value === null || trim((string) $value) === '') return null;

        if (ctype_digit((string) $value)) {
            $term = TaxonomyTerm::where('active', true)->find((int) $value);
            if ($term) return $term;
        }

        return TaxonomyTerm::where('active', true)
            ->where(fn (Builder $query) => $query->where('slug', $value)->orWhere('name', $value))
            ->first();
    }

    public function descendantIds(TaxonomyTerm|int $term): array
    {
        $termId = $term instanceof TaxonomyTerm ? (int) $term->id : (int) $term;
        $terms = $this->activeTerms();
        if (! $terms->has($termId)) return [];

        return $this->descendantsFor($termId, $this->children($terms));
    }

    public function constrain(Builder $query, string $section, TaxonomyTerm $term): Builder
    {
        $ids = $this->descendantIds($term);
        if ($ids === []) return $query->whereRaw('1 = 0');

        return $query->whereIn($query->qualifyColumn('id'), DB::table('taxonomy_assignments')
            ->select('subject_id')
            ->where('subject_type', $this->subjectType($section))
            ->whereIn('taxonomy_term_id', $ids));
    }

    public function filters(string $section): array
    {
        $terms = $this->activeTerms();
        if ($terms->isEmpty()) return [];

        $subjects = $this->assignedSubjects($section, $terms->keys()->all());
        $rows = $this->summaries($terms, [$section => $subjects], $section);
        $coverIds = $terms->where('kind', 'category')->pluck('cover_media_id')->filter()->unique()->all();
        $covers = Media::visibleLibrary()->where('kind', 'image')->whereIn('id', $coverIds)->get()->keyBy('id');

        return array_map(function (array $row) use ($terms, $covers) {
            $category = $row['kind'] === 'category' ? $terms->get($row['id']) : $this->categoryForTopic($terms, $row['id']);
            return [...$row,
                'category_id' => $category?->id,
                'category_name' => $category?->name,
                'cover_url' => $row['kind'] === 'category' && $category?->cover_media_id
                    ? $covers->get($category->cover_media_id)?->publicUrl() : null,
            ];
        }, $rows);
    }

    public function homeTopics(?int $limit = null): array
    {
        $terms = $this->activeTerms();
        if ($terms->isEmpty()) return [];

        $assignments = [];
        foreach (self::HOME_SECTIONS as $section) $assignments[$section] = $this->assignedSubjects($section, $terms->keys()->all());

        $topics = array_values(array_filter(
            $this->summaries($terms, $assignments),
            fn (array $row) => $row['kind'] === 'topic',
        ));

        if ($limit !== null) $topics = array_slice($topics, 0, max(1, $limit));
        $categories = [];
        foreach ($topics as $topic) $categories[$topic['id']] = $this->categoryForTopic($terms, (int) $topic['id']);
        $coverIds = collect($categories)->filter()->pluck('cover_media_id')->filter()->unique()->all();
        $covers = Media::visibleLibrary()->where('kind', 'image')->whereIn('id', $coverIds)->get()->keyBy('id');

        return array_map(function (array $row) use ($categories, $covers) {
            $sections = [];
            foreach (self::HOME_SECTIONS as $section) $sections[$section] = $row['sections'][$section] ?? [
                'count' => 0,
                'url' => route('public.'.$section, ['taxonomy' => $row['slug']]),
            ];
            $category = $categories[$row['id']];
            return [
                'id' => $row['id'],
                'slug' => $row['slug'],
                'name' => $row['name'],
                'category_id' => $category?->id,
                'category_name' => $category?->name,
                'category_cover_url' => $category?->cover_media_id ? $covers->get($category->cover_media_id)?->publicUrl() : null,
                'total' => $row['count'],
                'sections' => $sections,
            ];
        }, $topics);
    }

    public function homeCategories(): array
    {
        return TaxonomyTerm::where('active', true)->where('kind', 'category')->orderBy('name')
            ->get(['id', 'name'])->map(fn (TaxonomyTerm $term) => [
                'id' => (int) $term->id,
                'name' => $term->name,
            ])->sort(fn (array $left, array $right) => strnatcasecmp($left['name'], $right['name']))
            ->values()->all();
    }

    public function directoryShelves(): array
    {
        $terms = $this->activeTerms();
        $published = collect($this->homeTopics())->keyBy('id');
        $shelves = [];
        foreach ($terms->where('kind', 'category')->sort(fn (TaxonomyTerm $left, TaxonomyTerm $right) => strnatcasecmp($left->name, $right->name)) as $category) {
            $shelves[(int) $category->id] = ['name' => $category->name, 'books' => []];
        }
        foreach ($terms->where('kind', 'topic') as $topic) {
            $category = $this->categoryForTopic($terms, (int) $topic->id);
            $key = $category?->id ?? 'uncategorized';
            if (! isset($shelves[$key])) $shelves[$key] = ['name' => __('public.topics'), 'books' => []];
            $links = $published->get((int) $topic->id)['sections'] ?? [];
            $destination = collect(['beitraege', 'videos', 'buecher'])
                ->map(fn (string $section) => $links[$section] ?? null)
                ->filter(fn (?array $link) => ($link['count'] ?? 0) > 0)
                ->sortByDesc('count')->first();
            $shelves[$key]['books'][] = [
                'name' => $topic->name,
                'url' => $destination['url'] ?? route('public.beitraege', ['taxonomy' => $topic->slug]),
            ];
        }

        return array_values($shelves);
    }

    private function categoryForTopic(Collection $terms, int $topicId): ?TaxonomyTerm
    {
        $parentId = $terms->get($topicId)?->parent_id;
        $seen = [];
        while ($parentId && ! isset($seen[$parentId])) {
            $seen[$parentId] = true;
            $parent = $terms->get((int) $parentId);
            if (! $parent) break;
            if ($parent->kind === 'category') return $parent;
            $parentId = $parent->parent_id;
        }
        return null;
    }

    private function activeTerms(): Collection
    {
        return TaxonomyTerm::where('active', true)->orderBy('name')->get(['id', 'parent_id', 'name', 'slug', 'kind', 'cover_media_id'])->keyBy('id');
    }

    private function assignedSubjects(string $section, array $termIds): array
    {
        $assigned = [];
        foreach (DB::table('taxonomy_assignments')
            ->where('subject_type', $this->subjectType($section))
            ->whereIn('subject_id', $this->sectionQuery($section)->select('id'))
            ->whereIn('taxonomy_term_id', $termIds)
            ->get(['taxonomy_term_id', 'subject_id']) as $link) {
            $assigned[(int) $link->taxonomy_term_id][(string) $link->subject_id] = true;
        }

        return $assigned;
    }

    private function summaries(Collection $terms, array $assignmentsBySection, ?string $singleSection = null): array
    {
        $children = $this->children($terms);
        $rows = [];
        foreach ($terms as $term) {
            $descendants = $this->descendantsFor((int) $term->id, $children);
            $sections = [];
            $allSubjects = [];
            foreach ($assignmentsBySection as $section => $assignments) {
                $subjects = [];
                foreach ($descendants as $termId) foreach (array_keys($assignments[$termId] ?? []) as $subjectId) {
                    $subjects[$subjectId] = true;
                    $allSubjects[$section.':'.$subjectId] = true;
                }
                if ($subjects !== []) $sections[$section] = [
                    'count' => count($subjects),
                    'url' => route('public.'.$section, ['taxonomy' => $term->slug]),
                ];
            }
            $count = $singleSection === null ? count($allSubjects) : ($sections[$singleSection]['count'] ?? 0);
            if ($count === 0) continue;

            $rows[] = [
                'id' => (int) $term->id,
                'slug' => $term->slug,
                'name' => $term->name,
                'kind' => $term->kind,
                'parent_name' => $term->parent_id ? $terms->get((int) $term->parent_id)?->name : null,
                'count' => $count,
                'url' => $singleSection === null ? null : $sections[$singleSection]['url'],
                'sections' => $sections,
            ];
        }

        usort($rows, fn (array $left, array $right) => $right['count'] <=> $left['count'] ?: strnatcasecmp($left['name'], $right['name']));
        return $rows;
    }

    private function children(Collection $terms): array
    {
        $children = [];
        foreach ($terms as $term) if ($term->parent_id && $terms->has((int) $term->parent_id)) $children[(int) $term->parent_id][] = (int) $term->id;
        return $children;
    }

    private function descendantsFor(int $termId, array $children): array
    {
        $result = [];
        $pending = [$termId];
        while ($pending !== []) {
            $id = array_pop($pending);
            if (isset($result[$id])) continue;
            $result[$id] = true;
            foreach ($children[$id] ?? [] as $childId) $pending[] = $childId;
        }
        return array_keys($result);
    }

    private function sectionQuery(string $section): Builder
    {
        return $section === 'buecher' ? $this->books->query() : $this->content->forSection($section);
    }

    private function subjectType(string $section): string
    {
        return $section === 'buecher' ? 'product' : 'record';
    }
}
