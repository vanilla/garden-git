<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Tests;

use Garden\Git\CommitishInterace;
use Garden\Git\PartialBranch;
use Garden\Git\Tag;

/**
 * Tests for fetching and creating tags.
 */
class TagTest extends LocalGitTestCase {

    /**
     * Test that we can create and list tags.
     *
     * @return \Garden\Git\PartialCommit
     */
    public function testCreateAndListTags() {
        $this->dir()->touchFile("README.md");
        $firstCommit = $this->dir()->addAndCommitAll("Add README");
        $this->repo()->tagCommit($firstCommit, "readme-tag", "First tag");

        $this->assertInitialMasterTag();
        return $firstCommit;
    }

    /**
     * Assert that the initial tags from testCreateAndListTags() are still present on its commit.
     */
    private function assertInitialMasterTag() {
        $tags = $this->repo()->getTags(new PartialBranch("master"));
        $this->assertCount(1, $tags);
        $this->assertTagAuthors($tags[0], $this->dir()->getAuthor(), $this->dir()->getAuthor());

        $this->assertEquals('readme-tag', $tags[0]->getName());
        $this->assertEquals('First tag', $tags[0]->getMessage());
    }


    /**
     * Test that we can have different tags on different branches.
     *
     * @depends testCreateAndListTags
     */
    public function testTagsOnAnotherBranch() {
        // Make sure times passes.
        sleep(2);
        $branch = $this->repo()->createBranch('feature/new-branch');
        $this->repo()->switchBranch($branch);
        $this->dir()->touchFile("other-branch");
        $otherBranchCommit = $this->dir()->addAndCommitAll("On other branch");
        $this->repo()->git(["checkout", "master"]);

        $this->repo()->tagCommit($otherBranchCommit, "on-other-branch", "Tag is on a different branch");

        // Tags on master are still the same.
        $this->assertInitialMasterTag();

        // Assert that we have our own tag on our branch.
        $this->assertTags($otherBranchCommit, ['on-other-branch', 'readme-tag']);
    }

    /**
     * Test that we can create tags ahead of the current commit we are checking,
     * but will only receive tags from the history BEHIND our current commit we're checking.
     *
     * @depends testCreateAndListTags
     */
    public function testTagsAheadOfCurrentCommit(CommitishInterace $initialCommit) {
        $this->dir()->touchFile("commit2");

        // Sleep so that our time rolls over so the sort is consistent.
        sleep(1);
        $commitAhead = $this->dir()->addAndCommitAll("Commit #2");
        $this->repo()->tagCommit($commitAhead, "tag-on-commit2", "Tag on commit 2 message");
        $this->assertTags($initialCommit, ['readme-tag']);
        $this->assertTags($commitAhead, ['tag-on-commit2', 'readme-tag']);
    }

    /**
     * Test tag sorting behaviour.
     */
    public function testTagSorts() {
        $this->dir()->touchFile("file1");
        $commit1 = $this->dir()->addAndCommitAll("commit1");
        // Let the time roll over.
        sleep(1);
        $this->dir()->touchFile("file2");
        $commit2 = $this->dir()->addAndCommitAll("commit2");

        // Tag with version but do the tagging backwards.
        $this->repo()->tagCommit($commit2, "v2022.001");
        $this->repo()->tagCommit($commit1, "v2022.002");

        $this->assertTags($commit2, ["v2022.002", "v2022.001"], Tag::SORT_NEWEST_VERSION);
        $this->assertTags($commit2, ["v2022.001", "v2022.002"], Tag::SORT_NEWEST_COMMIT);
    }

    /**
     * Test that a tag can have separate commit and tag authors.
     */
    public function testDifferentTagAndCommitAuthors() {
        $this->dir()->touchFile("file1");
        $commit1 = $this->dir()->addAndCommitAll("commit1");
        $this->dir()->configureAuthor($this->dir()->getAltAuthor());
        $tag = $this->repo()->tagCommit($commit1, "tag");
        $this->assertAuthoredBy($tag, $this->dir()->getAltAuthor());
        $this->assertAuthoredBy($tag->getCommit(), $this->dir()->getAuthor());
    }

    /**
     * Test the branch not found exception.
     */
    public function testTagNotFound() {
        $this->expectExceptionMessage('Tag Not Found: \'bad-tag\'');
        $this->repo()->getTag('bad-tag');
    }

    /**
     * Test that we can use a tag as a commitish and branch from it.
     *
     * @return void
     */
    public function testBranchFromTag() {
        $this->dir()->touchFile("file1");
        $commit = $this->dir()->addAndCommitAll("commit1");
        $tag = $this->repo()->tagCommit($commit, "my-tag");
        $branch = $this->repo()->createBranch("tagged-branch", $tag);
        $this->assertEquals($commit->getCommitHash(), $branch->getCommitHash());
    }
}
