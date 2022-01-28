<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Tests;

use Garden\Git;

/**
 * Tests for branches.
 */
class BranchTest extends LocalGitTestCase {

    /**
     * Test that we can get our default branch.
     */
    public function testDefaultBranch() {
        // No branches exist before creating a commit.
        $branches = $this->repo()->getBranches();
        $this->assertBranches([]);
        $this->dir()->touchFile("init");
        $commit = $this->dir()->addAndCommitAll("init");
        $this->assertBranches([new Git\Branch("master", $commit)]);
    }

    /**
     * Test that we can create branches and read them.
     */
    public function testCreateBranch() {
        $this->dir()->touchFile("file1");
        $commit = $this->dir()->addAndCommitAll("Commit 1");
        $this->repo()->createBranch("my-branch", new Git\PartialBranch("master"));
        $this->dir()->touchFile("file2");
        $commit2 = $this->dir()->addAndCommitAll("Commit 2");
        $nestedBranch = $this->repo()->createBranch("nested/branch", $commit2);
        $altNestedBranch = $this->repo()->createBranch("nested/alt", $nestedBranch);
        $this->assertBranches([
            new Git\Branch('master', $commit2),
            new Git\Branch('my-branch', $commit),
            new Git\Branch('nested/branch', $commit2),
            new Git\Branch('nested/alt', $commit2),
        ]);
    }

    /**
     * Test switching branches.
     */
    public function testSwitchBranch() {
        $this->dir()->touchFile('file1');
        $this->dir()->addAndCommitAll('init');
        $notMasterBranch = $this->repo()->createBranch('not-master');
        $this->repo()->switchBranch($notMasterBranch);
        $this->dir()->touchFile('not-on-master');
        $this->dir()->addAndCommitAll('off-master');
        $this->dir()->assertFileExists('not-on-master');
        // Switch back to master.
        $this->repo()->switchBranch($this->repo()->getBranch('master'));
        $this->dir()->assertFileNotExists('not-on-master');
    }

    /**
     * @depends testSwitchBranch
     */
    public function testCurrentBranch() {
        $this->assertEquals('master', $this->repo()->currentBranch()->getName());
        $this->repo()->switchBranch($this->repo()->getBranch('not-master'));
        $this->assertEquals('not-master', $this->repo()->currentBranch()->getName());
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
     * @return array[]
     */
    public function provideParseErrors(): array {
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
