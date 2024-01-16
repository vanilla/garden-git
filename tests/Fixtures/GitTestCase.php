<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Tests\Fixtures;

use Garden\Git;
use Garden\Git\Tag;
use PHPUnit\Framework\TestCase;


/**
 * Test case for working with one of 2 git directories.
 */
class GitTestCase extends TestCase {

    /** @var TestGitDirectory */
    protected static $dir1;

    /** @var TestGitRepository */
    protected static $repo1;

    /** @var TestGitDirectory */
    protected static $dir2;

    /** @var TestGitRepository */
    protected static $repo2;

    /**
     * Reset the repositories.
     */
    protected function resetRepos() {
        static::$dir1 = new TestGitDirectory();
        static::$repo1 = static::$dir1->getRepository();
        static::$dir2 = new TestGitDirectory();
        static::$repo2 = static::$dir2->getRepository();
    }

    /**
     * Reset repositories if we aren't starting up a dependent test.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!$this->hasDependencyInput()) {
            $this->resetRepos();
        }
    }

    /**
     * @return TestGitRepository
     */
    protected function repo(): TestGitRepository {
        return self::$repo1;
    }

    /**
     * @return TestGitDirectory
     */
    protected function dir(): TestGitDirectory {
        return self::$dir1;
    }

    /**
     * @return TestGitRepository
     */
    protected function repo2(): TestGitRepository {
        return self::$repo2;
    }

    /**
     * @return TestGitDirectory
     */
    protected function dir2(): TestGitDirectory {
        return self::$dir2;
    }

    ///
    /// Assertions
    ///

    /**
     * Assert something has a specific author.
     *
     * @param Git\AuthorableInterface $authorable
     * @param Git\Author|null $expectedAuthor
     */
    protected function assertAuthoredBy(Git\AuthorableInterface $authorable, ?Git\Author $expectedAuthor) {
        $this->assertEquals($expectedAuthor, $authorable->getAuthor());
    }

    /**
     * Assert that we have a set of tags.
     *
     * @param Git\CommitishInterace $branch The commit to check tags on.
     * @param array $expectedTagNames The expected tag names.
     * @param string $sort What tag sort mode to fetch with.
     */
    public function assertTags(Git\CommitishInterace $branch, array $expectedTagNames, string $sort = Tag::SORT_NEWEST_COMMIT) {
        $tags = $this->repo()->getTags($branch, $sort);
        $actualNames = [];
        foreach ($tags as $tag) {
            $actualNames[] = $tag->getName();
        }
        $this->assertSame($expectedTagNames, $actualNames);
    }

    /**
     * Assert a tags tag and commit authors.
     *
     * @param Tag $tag
     * @param Git\Author|null $expectedTagAuthor
     * @param Git\Author|null $expectedCommitAuthor
     */
    protected function assertTagAuthors(Git\Tag $tag, ?Git\Author $expectedTagAuthor, ?Git\Author $expectedCommitAuthor) {
        $this->assertAuthoredBy($tag, $expectedTagAuthor);
        $this->assertAuthoredBy($tag->getCommit(), $expectedTagAuthor);
    }
}
