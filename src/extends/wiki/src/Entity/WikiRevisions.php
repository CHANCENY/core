<?php

namespace Simp\Core\extends\wiki\src\Entity;

use DateMalformedStringException;
use DI\DependencyException;
use DI\NotFoundException;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\extends\wiki\src\enum\WikiStatusEnum;
use Simp\Core\modules\services\Service;
use IteratorAggregate;
use ArrayIterator;
use Throwable;

class WikiRevisions implements IteratorAggregate
{
    /**
     * @var WikiRevision[]
     */
    protected array $revisions = [];

    protected Wiki|int $wiki;

    /**
     * @throws DependencyException
     * @throws DateMalformedStringException
     * @throws NotFoundException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __construct(int|Wiki $wiki)
    {
        $this->wiki = is_object($wiki) ? $wiki : Wiki::load($wiki);
        $this->loadRevisions();
    }

    /**
     * Load all revisions from database
     */
    protected function loadRevisions(): void
    {
        $wikiId = is_int($this->wiki) ? $this->wiki : $this->wiki->id();
        $stmt = Service::get('connection')->prepare(
            "SELECT id FROM wiki_entities_revision WHERE wiki_id = :id ORDER BY id ASC"
        );
        $stmt->bindValue(':id', $wikiId);
        $stmt->execute();

        $this->revisions = [];
        foreach ($stmt->fetchAll() as $rev) {
            $this->revisions[] = new WikiRevision($rev['id']);
        }
    }

    /**
     * Add a new revision to the wiki with the given content, status, and author.
     *
     * @param int|null $authorId Optional author ID, defaults to current user
     * @throws DateMalformedStringException
     * @throws DependencyException
     * @throws NotFoundException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     */
    public function addRevision(string $content, WikiStatusEnum $status, ?int $authorId = null): static
    {
        // Determine author
        $authorId ??= Service::get('current_user')->id();
        $content = $this->sanitizeContent($content);

        // Insert revision
        $stmt = Service::get('connection')->prepare(
            "INSERT INTO wiki_entities_revision (content, status, wiki_id, uid) 
         VALUES (:content, :status, :wiki_id, :uid)"
        );
        $stmt->bindValue(':content', $content);
        $stmt->bindValue(':status', $status->value);
        $stmt->bindValue(':wiki_id', is_int($this->wiki) ? $this->wiki : $this->wiki->id());
        $stmt->bindValue(':uid', $authorId);
        $stmt->execute();

        $revisionId = (int) Service::get('connection')->lastInsertId();
        $this->revisions[] = new WikiRevision($revisionId);

        // Add the author to wiki_authors if not already added
        $wikiId = is_int($this->wiki) ? $this->wiki : $this->wiki->id();
        $checkStmt = Service::get('connection')->prepare(
            "SELECT COUNT(*) FROM wiki_authors WHERE wiki_id = :wiki_id AND uid = :uid"
        );
        $checkStmt->bindValue(':wiki_id', $wikiId);
        $checkStmt->bindValue(':uid', $authorId);
        $checkStmt->execute();

        $exists = (int) $checkStmt->fetchColumn();

        if ($exists === 0) {
            $insertAuthorStmt = Service::get('connection')->prepare(
                "INSERT INTO wiki_authors (wiki_id, uid) VALUES (:wiki_id, :uid)"
            );
            $insertAuthorStmt->bindValue(':wiki_id', $wikiId);
            $insertAuthorStmt->bindValue(':uid', $authorId);
            $insertAuthorStmt->execute();
        }

        return $this;
    }

    /**
     * Get all revisions as an array
     * @return WikiRevision[]
     */
    public function all(): array
    {
        return $this->revisions;
    }

    /**
     * Get first revision
     */
    public function first(): ?WikiRevision
    {
        return $this->revisions[0] ?? null;
    }

    /**
     * Get the last revision
     */
    public function last(): ?WikiRevision
    {
        return end($this->revisions) ?: null;
    }

    /**
     * Count revisions
     */
    public function count(): int
    {
        return count($this->revisions);
    }

    /**
     * Filter revisions by status
     */
    public function filterByStatus(WikiStatusEnum $status): array
    {
        return array_filter($this->revisions, fn(\Simp\Core\extends\wiki\src\Entity\WikiRevision $rev): bool => $rev->getStatus() === $status);
    }

    /**
     * Filter revisions by creation date range
     */
    public function filterByDate(\DateTime $from, \DateTime $to): array
    {
        return array_filter($this->revisions, fn(\Simp\Core\extends\wiki\src\Entity\WikiRevision $rev): bool =>
            $rev->getCreatedAt() >= $from && $rev->getCreatedAt() <= $to
        );
    }

    /**
     * Compute diff between two revisions
     */
    public function diff(WikiRevision $a, WikiRevision $b): string
    {
        $old = explode("\n", $a->getContent()->__toString());
        $new = explode("\n", $b->getContent()->__toString());
        $diff = [];

        foreach ($new as $i => $line) {
            if (!isset($old[$i]) || $old[$i] !== $line) {
                $diff[] = '+ ' . $line;
            }
        }

        foreach ($old as $i => $line) {
            if (!isset($new[$i]) || $line !== $new[$i] ?? null) {
                $diff[] = '- ' . $line;
            }
        }

        return implode("\n", $diff);
    }

    /**
     * Get revision by ID
     */
    public function getRevisionById(int $id): ?WikiRevision
    {
        return array_find($this->revisions, fn($rev): bool => $rev->getId() === $id);
    }

    /**
     * Refresh revisions from a database
     */
    public function refresh(): static
    {
        $this->loadRevisions();
        return $this;
    }

    /**
     * Implement IteratorAggregate
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->revisions);
    }

    /**
     * Get all revisions as an array of WikiRevision objects
     *
     * @return WikiRevision[]
     */
    public function getAllRevisions(): array
    {
        return $this->revisions;
    }

    /**
     * Get a detailed revision history for display or processing
     *
     * Each item contains:
     * - revision object
     * - author object (nullable)
     * - created_at
     * - status
     */
    public function getRevisionHistory(): array
    {
        $history = [];

        foreach ($this->revisions as $revision) {
            $history[] = [
                'revision'   => $revision,
                'author'     => $revision->getAuthor(), // User object or null
                'created_at' => $revision->getCreatedAt(),
                'status'     => $revision->getStatus(),
                'summary'    => $revision->getSummary(100), // optional short preview
            ];
        }

        return $history;
    }

    /**
     * Get the latest revision (most recent)
     */
    public function getLatestRevision(): ?WikiRevision
    {
        if ($this->revisions === []) {
            return null;
        }

        return end($this->revisions);
    }

    /**
     * Get a revision by index (0 = oldest)
     */
    public function getRevisionByIndex(int $index): ?WikiRevision
    {
        if (!isset($this->revisions[$index])) {
            return null;
        }

        return $this->revisions[$index];
    }

    /**
     * Compare two revisions by ID or index
     *
     * @param WikiRevision|int $revA First revision object or index (0 = oldest)
     * @param WikiRevision|int $revB Second revision object or index (0 = oldest)
     * @return string Diff output
     */
    public function compareRevisions(WikiRevision|int $revA, WikiRevision|int $revB): string
    {
        // Resolve revisions if indices are passed
        if (is_int($revA)) {
            $revA = $this->getRevisionByIndex($revA);
        }

        if (is_int($revB)) {
            $revB = $this->getRevisionByIndex($revB);
        }

        if (!$revA instanceof \Simp\Core\extends\wiki\src\Entity\WikiRevision || !$revB instanceof \Simp\Core\extends\wiki\src\Entity\WikiRevision) {
            return '';
        }

        $contentA = $revA->getContent()->__toString();
        $contentB = $revB->getContent()->__toString();

        // Use xdiff if available
        if (function_exists('xdiff_string_diff')) {
            $diff = xdiff_string_diff($contentA, $contentB, 1);
            if ($diff === false) {
                return "No differences found.";
            }

            return $diff;
        }

        // Fallback: line-by-line comparison
        $linesA = explode("\n", $contentA);
        $linesB = explode("\n", $contentB);

        $output = '';
        $maxLines = max(count($linesA), count($linesB));

        for ($i = 0; $i < $maxLines; $i++) {
            $lineA = $linesA[$i] ?? '';
            $lineB = $linesB[$i] ?? '';

            if ($lineA !== $lineB) {
                $output .= "- {$lineA}\n+ {$lineB}\n";
            }
        }

        return $output !== '' && $output !== '0' ? $output : "No differences found.";
    }

    /**
     * Get all revision objects
     *
     * @return WikiRevision[]
     */
    public function getRevisions(): array
    {
        return $this->revisions;
    }

    /**
     * Set the content, removing unsafe tags while keeping safe HTML intact.
     */
    public function sanitizeContent(string $content): string
    {
        // Remove <form> tags
        $content = preg_replace('#<form[^>]*>(.*?)</form>#is', '', $content);

        // Remove <script> tags
        $content = preg_replace('#<script[^>]*>(.*?)</script>#is', '', (string) $content);

        // Remove <iframe> tags
        $content = preg_replace('#<iframe[^>]*>(.*?)</iframe>#is', '', (string) $content);

        // Remove <object> tags
        return preg_replace('#<object[^>]*>(.*?)</object>#is', '', (string) $content);
    }

}
