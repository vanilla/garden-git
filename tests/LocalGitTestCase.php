<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Tests;

use Garden\Git;
use Garden\Git\Tag;
use Garden\Git\Tests\Fixtures\TestGitDirectory;
use PHPUnit\Framework\TestCase;

class LocalGitTestCase extends TestCase {

    /** @var TestGitDirectory */
    protected static $gitDirectory;

    /** @var Git\Repository */
    protected static $repository;


    public function __construct($name = null, array $data = [], $dataName = '') {
        parent::__construct($name, $data, $dataName);
        $this->resetRepo();
    }

    public function resetRepo() {
        static::$gitDirectory = new TestGitDirectory();
        static::$repository = static::$gitDirectory->getRepository();
    }

    protected function setUp(): void {
        parent::setUp();
        if (!$this->hasDependencies()) {
            $this->resetRepo();
        }
    }

    public function assertTags(Git\CommitishInterace $branch, array $expectedTagNames, string $sort = Tag::SORT_NEWEST_COMMIT) {
        $tags = $this->repo()->getTags($branch, $sort);
        $actualNames = [];
        foreach ($tags as $tag) {
            $actualNames[] = $tag->getName();
        }
        $tagDates = $this->tagDates($tags);
        $this->assertSame($expectedTagNames, $actualNames);
    }

    /**
     * @param Tag[] $tags
     * @return array[]
     */
    private function tagDates(array $tags) {
        return [
            'commits' => array_map(function (Tag $tag) { return $tag->getCommit()->getDate()->format(\DateTimeInterface::RFC3339);}, $tags),
            'tags' => array_map(function (Tag $tag) { return $tag->getDate()->format(\DateTimeInterface::RFC3339);}, $tags),
        ];
    }


    protected function repo(): Git\Repository {
        return self::$repository;
    }

    protected function dir(): TestGitDirectory {
        return self::$gitDirectory;
    }

    protected function assertAuthoredBy(Git\AuthorableInterface $authorable, ?Git\Author $expectedAuthor) {
        $this->assertEquals($expectedAuthor, $authorable->getAuthor());
    }

    protected function assertTagAuthors(Git\Tag $tag, ?Git\Author $expectedTagAuthor, ?Git\Author $expectedCommitAuthor) {
        $this->assertAuthoredBy($tag, $expectedTagAuthor);
        $this->assertAuthoredBy($tag->getCommit(), $expectedTagAuthor);
    }
}
