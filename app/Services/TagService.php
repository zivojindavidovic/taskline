<?php

namespace App\Services;

use App\Models\Tag;

/**
 * Owns the workspace-scoped global tag vocabulary. Every place a task gains a
 * tag funnels its names through here, so a freshly typed tag is registered once
 * and then offered as a suggestion across the whole workspace forever — even
 * after the task that introduced it loses the tag or is deleted.
 */
class TagService
{
    /**
     * Register each given tag name into the workspace pool (idempotent).
     *
     * @param  array<int, string>  $names
     */
    public function registerForWorkspace(int $workspaceId, array $names): void
    {
        foreach ($this->normalizeNames($names) as $name) {
            Tag::firstOrCreate([
                'workspace_id' => $workspaceId,
                'name'         => $name,
            ]);
        }
    }

    /**
     * The full, sorted list of tag names available in a workspace.
     *
     * @return array<int, string>
     */
    public function allForWorkspace(int $workspaceId): array
    {
        return Tag::where('workspace_id', $workspaceId)
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * Normalize a raw tag to its canonical form — trimmed, lower-cased, internal
     * whitespace collapsed to single dashes. Mirrors the frontend `normalizeTag`
     * so a tag typed in the UI and the same tag arriving via the API collapse to
     * one stored value.
     */
    public function normalize(string $name): string
    {
        return strtolower(preg_replace('/\s+/', '-', trim($name)));
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    private function normalizeNames(array $names): array
    {
        return collect($names)
            ->map(fn ($name) => $this->normalize((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
