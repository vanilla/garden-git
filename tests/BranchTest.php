<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Tests;

use Garden\Git;
use Garden\Git\Tests\Fixtures\GitTestCase;

/**
 * Tests for branches.
 */
class BranchTest extends GitTestCase {

    /**
     * Test that we can get our default branch.
     */
    public function testDefaultBranch() {
        // No branches exist before creating a commit.
        $branches = $this->repo()->getBranches();
        $this->assertBranches([]);
        $this->dir()->touchFile("init");
        $commit = $this->dir()->addAndCommitAll("init");
        $this->assertBranches([new Git\Branch("main", $commit)]);
    }

    /**
     * Test that we can create branches and read them.
     */
    public function testCreateBranch() {
        $this->dir()->touchFile("file1");
        $commit = $this->dir()->addAndCommitAll("Commit 1");
        $this->repo()->createBranch("my-branch", new Git\PartialBranch("main"));
        $this->dir()->touchFile("file2");
        $commit2 = $this->dir()->addAndCommitAll("Commit 2");
        $nestedBranch = $this->repo()->createBranch("nested/branch", $commit2);
        $altNestedBranch = $this->repo()->createBranch("nested/alt", $nestedBranch);
        $this->assertBranches([
            new Git\Branch('main', $commit2),
            new Git\Branch('my-branch', $commit),
            new Git\Branch('nested/branch', $commit2),
            new Git\Branch('nested/alt', $commit2),
        ]);
    }

    /**
     * Test that we validate existing branch names.
     * @depends testCreateBranch
     */
    public function testAddExistingBranch() {
        $this->expectExceptionMessage('Branch already exists');
        $this->repo()->createBranch("my-branch", new Git\Head());
    }

    /**
     * Test switching branches.
     */
    public function testSwitchBranch() {
        $this->dir()->touchFile('file1');
        $this->dir()->addAndCommitAll('init');
        $notmainBranch = $this->repo()->createBranch('not-main');
        $this->repo()->switchBranch($notmainBranch);
        $this->dir()->touchFile('not-on-main');
        $this->dir()->addAndCommitAll('off-main');
        $this->dir()->assertFileExists('not-on-main');
        // Switch back to main.
        $this->repo()->switchBranch($this->repo()->getBranch('main'));
        $this->dir()->assertFileNotExists('not-on-main');
    }

    /**
     * @depends testSwitchBranch
     */
    public function testCurrentBranch() {
        $this->assertEquals('main', $this->repo()->currentBranch()->getName());
        $this->repo()->switchBranch($this->repo()->getBranch('not-main'));
        $this->assertEquals('not-main', $this->repo()->currentBranch()->getName());
    }

    /**
     * Test the branch not found exception.
     */
    public function testNotFound() {
        $this->expectExceptionMessage('Branch Not Found: \'bad-branch\'');
        $this->repo()->getBranch('bad-branch');
    }

    /**
     * Test that we can create a branch from a commit.
     */
    public function testBranchFromCommit() {
        $this->dir()->touchFile('file');
        $commit = $this->dir()->addAndCommitAll('init');
        $this->assertEquals('init', $commit->getMessage());
        $branch = $this->repo()->createBranch('branchname', $commit);
        $this->assertEquals($commit->getCommitish(), $branch->getCommitHash());
    }

    /**
     * Test the commit not found message.
     */
    public function testCommitNotFound() {
        $this->expectExceptionMessage('Commit Not Found: \'not-a-commit\'');
        $this->repo()->getCommit("not-a-commit");
    }

    /**
     * Test parsing errors for branches.
     *
     * @param string $input
     * @param string $expectedMessage
     *
     * @dataProvider provideParseErrors
     */
    public function testParseErrors(string $input, string $expectedMessage) {
        $this->expectExceptionMessage($expectedMessage);
        Git\Branch::fromGitOutputLine($input);
    }

    /**
     * Test deletion of a branch.
     */
    public function testDeleteBranch() {
        $this->dir()->touchFile('file');
        $commit = $this->dir()->addAndCommitAll('init');
        $branch = $this->repo()->createBranch("my-branch", $commit);
        $mainBranch = $this->repo()->getBranch('main');
        $this->assertBranches([$mainBranch, $branch]);
        $this->repo()->deleteBranch($branch, false);
        $this->assertBranches([$mainBranch]);
    }

    /**
     * @depends testDeleteBranch
     */
    public function testDeleteCheckedOutBranch() {
        $this->expectExceptionMessage('Cannot delete the checked out branch');
        $this->repo()->deleteBranch($this->repo()->currentBranch());
    }

    /**
     * @return array[]
     */
    public static function provideParseErrors(): array {
        return [
            [
                'some-branch | 42dsf44 | refs/head/other',
                'Only parsing of remote upstreams is implemented.',
            ],
            [
                'some-branch | 4234dsf | refs/remotes/branch-name',
                'Could not find a remote name in remote upstream'
            ],
            [
                'some-branch | refs/remotes/remotename/branch-name',
                'Expected 3 parts'
            ]
        ];
    }

    /**
     * Assert that a set of branches are the current set of branches.
     *
     * @param Git\Branch[] $expectedBranches
     */
    private function assertBranches(array $expectedBranches) {
        $actualBranches = $this->repo()->getBranches();
        $this->assertEquals($this->sortBranches($expectedBranches), $this->sortBranches($actualBranches));
    }

    /**
     * Apply a stable sorting of branches.
     *
     * @param Git\Branch[] $branches
     *
     * @return array
     */
    private function sortBranches(array $branches): array {
        usort($branches, function ($branchA, $branchB) {
            return $branchA->getName() <=> $branchB->getName();
        });
        return $branches;
    }
}
