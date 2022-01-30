<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Tests;

use Garden\Git;

/**
 * Tests of git remotes.
 */
class RemoteTest extends LocalGitTestCase {

    /**
     * Test adding a remote.
     */
    public function testAddRemote() {
        $this->dir()->touchFile("repo1file");
        $dir1Commit = $this->dir()->addAndCommitAll('repo1');
        $this->repo()->createBranch('repo1-branch', $dir1Commit);
        $this->dir2()->touchFile("repo2file");
        $dir2Commit = $this->dir2()->addAndCommitAll('repo2');
        $this->repo2()->createBranch('repo2-branch', $dir2Commit);

        $remote = $this->repo()->addRemote(new Git\Remote('repo2', $this->repo2()->absolutePath()));
        $this->assertEquals('repo2', $remote->getName());
        $this->assertEquals($this->repo2()->absolutePath(), $remote->getUri());
        $this->assertTrue($remote->canFetch());
        $this->assertTrue($remote->canPush());
    }

    /**
     * Test error handling for existing remote.
     *
     * @depends testAddRemote
     */
    public function testAddExistingRemote() {
        $this->expectExceptionMessage('Remote already exists');
        $this->repo()->addRemote(new Git\Remote('repo2', './somePath'));
    }

    /**
     * Test fetching remotes.
     *
     * @depends testAddRemote
     */
    public function testGetRemotes() {
        // Get a single remote.
        $remotes = $this->repo()->getRemotes();
        $this->assertCount(1, $remotes);
        $remote = $this->repo()->getRemote('repo2');
        $this->assertEquals($remote, $remotes[0]);
    }

    /**
     * @depends testAddRemote
     */
    public function testCheckoutBranchFromRemote() {
        $repo2Remote = $this->repo()->getRemote('repo2');
        $branch = $this->repo()->createBranchFromRemote(
            new Git\PartialBranch('repo2-branch-copied'),
            $repo2Remote,
            'repo2-branch'
        );
        $this->repo()->switchBranch($branch);
        $this->dir()->assertFileExists('repo2file');
        $this->assertEquals($repo2Remote->getName(), $branch->getRemoteName());
        $this->assertEquals('repo2-branch', $branch->getRemoteBranchName());
    }

    /**
     * Make sure we validate existing branches.
     * @depends testCheckoutBranchFromRemote
     */
    public function testCheckoutBranchFromRemoteDuplicate() {
        $this->expectExceptionMessage('Branch already exists');
        $this->testCheckoutBranchFromRemote();
    }

    /**
     * @depends testCheckoutBranchFromRemote
     */
    public function testPushExistingToRemote() {
        $this->dir()->touchFile('added-from-repo1');
        $repo1Commit = $this->dir()->addAndCommitAll('added-from-repo1');
        $this->repo()->pushBranch(
            $this->repo()->getBranch('repo2-branch-copied'),
            $this->repo()->getRemote('repo2')
        );

        $repo2Branch = $this->repo2()->getBranch('repo2-branch');
        $this->repo2()->switchBranch($repo2Branch);
        $this->dir2()->assertFileExists('added-from-repo1');
        $this->assertEquals($repo1Commit->getCommitish(), $repo2Branch->getCommitHash());
    }

    /**
     * @depends testCheckoutBranchFromRemote
     */
    public function testDeletionOfBranchFromRemote() {
        // Make sure we not on the branch being deleted.
        $this->repo()->switchBranch($this->repo()->getBranch('master'));
        $this->repo2()->switchBranch($this->repo2()->getBranch('master'));

        // Check counts before.
        $this->assertCount(3, $this->repo()->getBranches());
        $this->assertCount(2, $this->repo2()->getBranches());

        // Delete the branch.
        $this->repo()->deleteBranch(
            $this->repo()->getBranch('repo2-branch-copied'),
            true
        );

        // 1 less branch after .
        $this->assertCount(2, $this->repo()->getBranches());
        $this->assertCount(1, $this->repo2()->getBranches());
    }

    /**
     * Test the branch not found exception.
     */
    public function testRemoteNotFound() {
        $this->expectExceptionMessage('Remote Not Found: \'bad-remote\'');
        $this->repo()->getRemote('bad-remote');
    }

    public function tryFetchPushOnlyRemote() {
        $remote = $this->repo()->addRemote(new Git\Remote('repo2', $this->repo2()->absolutePath()));

    }
}
