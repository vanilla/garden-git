# vanilla/garden-git

An object-oriented PHP library for working Git.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
![GitHub release (latest SemVer)](https://img.shields.io/github/v/release/vanilla/garden-git?label=release)
[![CircleCI](https://circleci.com/gh/vanilla/garden-git/tree/master.svg?style=svg)](https://circleci.com/gh/vanilla/garden-git/tree/master)
[![codecov](https://codecov.io/gh/vanilla/garden-git/branch/master/graph/badge.svg?token=z1AGuq5H7w)](https://codecov.io/gh/vanilla/garden-git)

## Installation

```shell
composer require vanilla/garden-git
```

## Features

- Ability to call arbitrary git commands with nicely wrapped process output.
- Object-oriented wrappers around common git operations.
  - Branches
  - Commits
  - Remotes
  - Tags
  - Authors
  - File staging and restoration.
- PHPUnit test harness w/ 100% test coverage.

## Usage

**Common Patterns**

- All methods may throw a `Garden\Git\Exception\GitException` if there is a problem.
- `find` methods return null if an item is not found.
- `get` methods throw if a `Garden\Git\Exception\NotFoundException` item is not found.

```php
use Garden\Git;

// Will throw if path isn't the root of a git repo.
$repo = new Git\Repository('/path/to/repo');

// Run any git command.
// Will throw a `GitException` with the failed process output
// if git returns a non-0 exit code.
// Otherwise returns the string output from git.
$repo->git(['rebase', 'master'])

// Commits
$newCommit = $repo->commit("Commit")
$existingCommit = $repo->getCommit("asdf4asd31kl3jkll41");

// Working with a commit.
$newCommit->getAuthor();
$newCommit->getCommitHash();
$newCommit->getDate();
$newCommit->getMessage();

// Get commit author
$author = $existingCommit->getAuthor();
$author->getEmail();
$author->getName();

// List Branches
$branches = $repo->getBranches();

// A string, Git\Branch or Git\PartialBranch can be used here.
$existingBranch = $repo->findBranch('branch/name');
$existingBranch = $repo->getBranch(new Git\PartialBranch('branch/name'));

// Working with branches
$existingBranch->getName();
$existingBranch->getCommitHash();
$existingBranch->getRemoteBranchName();
$existingBranch->getRemoteName();

// Create a branch
$newBranch = $repo->createBranch("new/branch-name", new Git\Head());
$newBranch = $repo->createBranch("new/branch-name", $existingBranch);
$newBranch = $repo->createBranch("new/branch-name", new Git\PartialBranch("old/branch-name"));
$newBranch = $repo->createBranch("new/branch-name", $existingCommit);

// Delete branch
$repo->deleteBranch($newBranch);

// List Tags
$tags = $repo->getTags();
$tags = $repo->getTags($existingCommit); // Get only tags reachable on this commit.
$tags = $repo->getTags($existingBranch); // Get only tags reachable on a branch.

// Sorting
$tags = $repo->getTags(new Git\Head(), Git\Tag::SORT_NEWEST_COMMIT);
$tags = $repo->getTags(new Git\Head(), Git\Tag::SORT_NEWEST_VERSION);

// Lookup a tag
$existingTag = $repo->findTag("v1.2.0");
$existingTag = $repo->getTag("v1.2.0");

// Working with tags
$existingTag->getName();
$existingTag->getMessage();
$existingTag->getDate();
$existingTag->getAuthor();
$existingTag->getCommit();

// Create a tag
$newTag = $repo->tagCommit($existingCommit, "v1.3.0", "Tag description");
$newTag = $repo->tagCommit($existingBranch, "v1.3.0", "Tag description");
$newTag = $repo->tagCommit(new Git\Head(), "v1.3.0", "Tag description");

// Get the info from a tag.
$commit = $newTag->getCommit();
$commitAuthor = $newTag->getCommit()->getAuthor();
$tagAuthor = $newTag->getAuthor();

// Delete a tag
$repo->deleteTag($newTag);

// Get remotes
$remotes = $repo->getRemotes();
$existingRemote = $repo->findRemote("origin");
$existingRemote = $repo->getRemote("origin");

// Working with  remote.
$existingRemote->getName();
$existingRemote->getUri();
$existingRemote->canFetch();
$existingRemote->canPush();

// Create remote
$newRemote = $repo->addRemote(new Git\Remote(
    "alt-origin",
    "git@github.com:vanilla/vanilla-cloud.git"
))

// Fetch data from the remote.
// You'll likely want to run this after creating a remote.
$repo->fetchFromRemote($newRemote);
foreach ($repo->fetchFromRemoteIterator($remote) as $gitOutputLine) {
    // Allows you to render some progress during the fetch. 
}

// Remote a remote
$repo->removeRemote($newRemote);

// Pull a branch from a remote.
// Git itself often uses the remote branch name as your local name automatically.
// This method makes you explicitly declare that.
$branch = $repo->createBranchFromRemote(
    new Git\PartialBranch("local-branch-name"),
    $existingRemote,
    "remote-branch-name",
);

// Push to a remote.
$repo->pushBranch($branch, $existingRemote);

// Delete branch locally and on remote.
$repo->deleteBranch($branch, true);

// Statuses
$status = $repo->getStatus();
$files = $status->getFiles();
$files = $status->getAdded();
$files = $status->getDeleted();
$files = $status->getModified();
$files = $status->getIgnored();
$files = $status->getStagedFiles();
$files = $status->getUnstagedFiles();
$files = $status->hasChanges();
$files = $status->hasUnstagedChanges();
$renamedfiles = $status->getRenamed();

// Working with files;
$files[0]->getPath();
$files[0]->hasStaged();
$files[0]->hasUnstaged();
$renamedFiles[0]->getOldPath();

// Manipulating staging area.
$status = $repo->stageFiles(["/dir1", "/dir2", "file1"]);
$status = $repo->unstageFiles(["/dir1", "/dir2", "file1"]);

// Copy files from another branch.
$status = $repo->restoreFiles(["/dir1", "/dir2", "file1"], $branch);

// Clear out all uncommitted file changes.
$status =$repo->resetFiles();
```
