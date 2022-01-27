<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git;

use Garden\Schema\Schema;

class Tag implements AuthorableInterface, CommitishInterace {

    public const SORT_NEWEST_COMMIT = "-*committerdate";
    public const SORT_NEWEST_VERSION = "-version:refname";

    /** @var string */
    private $name;

    /** @var Commit */
    private $commit;

    /** @var Author|null */
    private $author;

    /** @var \DateTimeInterface|null */
    private $date;

    /** @var string|null */
    private $message;

    ///
    /// Creating tags.
    ///

    /**
     * DI.
     *
     * @param string $name
     * @param Commit $commit
     * @param Author|null $author
     * @param \DateTimeInterface|null $date
     * @param string|null $message
     */
    public function __construct(string $name, Commit $commit, ?Author $author, ?\DateTimeInterface $date, ?string $message) {
        $this->name = $name;
        $this->commit = $commit;
        $this->author = $author;
        $this->date = $date;
        $this->message = $message;
    }

    /**
     * Get the format to use with `git for-each-ref` that we know how to parse.
     *
     * This format should return data in the following format:
     * @example Annotated Tag
     * tag: test-annotate
     * ref: refs/tags/test-annotate
     * commitSha: cd9d41b677056f9bfd71014e1118b5a1de0f4345
     * commitMessage: File 4
     * committerName: Adam Charron
     * committerEmail: adam@charrondev.com
     * commitDate: 2022-01-26T02:51:21-05:00
     * tagMessage: Annotated tag
     * taggerName: Adam Charron
     * taggerEmail: adam@charrondev.com
     * tagDate: 2022-01-26T01:20:54-05:00
     *
     * @example Lightweight Tag
     * tag: applied-on-other
     * ref: refs/tags/applied-on-other
     * commitSha: dab4e994af86dd54b3b727942db680aaf6a12681
     * commitMessage: File 2
     * committerName: Adam Charron
     * committerEmail: adam@charrondev.com
     * commitDate: 2022-01-26T01:20:54-05:00
     *
     * @return string
     */
    public static function getFormat(): string {
        // A few notes on this format.
        // This is specifically needed to normalize differences between annotated and lightweight tags.
        return <<<FORMAT
tag: %(refname:strip=2)
ref: %(refname)%(if)%(taggerdate)%(then)
commitHash: %(*objectname)
commitMessage: %(*subject)
commitDate: %(*committerdate:iso-strict)
committerName: %(*committername)
committerEmail: %(*committeremail:trim)
tagMessage: %(subject)
tagDate: %(taggerdate:iso-strict)
taggerName: %(taggername)
taggerEmail: %(taggeremail:trim)%(else)
commitHash: %(objectname)
commitMessage: %(subject)
commitDate: %(committerdate:iso-strict)
committerName: %(committername)
committerEmail: %(committeremail:trim)%(end)
FORMAT;
    }

    /**
     * Create a tag from a single output of `git for-each-ref` with the format from `getFormat()`.
     *
     * @param string $output The string output from git.
     *
     * @return Tag
     */
    public static function fromFormatOutput(string $output): Tag {
        $output = trim($output);
        // Split on newlines and treat each one how we would parse an http header.
        $data = [];
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $splitLine = explode(": ", $line);
            [$fieldName, $fieldValue] = $splitLine;
            $data[$fieldName] = trim($fieldValue);
        }
        $data = self::formattedOutputSchema()->validate($data);

        $commitAuthor = new Author($data['committerName'], $data['committerEmail']);
        $commit = new Commit(
            $data['commitHash'],
            $data['commitDate'],
            $commitAuthor,
            $data['commitMessage']
        );

        $tagAuthor = null;
        if ($data['taggerName']) {
            $tagAuthor = new Author($data['taggerName'], $data['taggerEmail']);
        }

        $tag = new Tag(
            $data['tag'],
            $commit,
            $tagAuthor,
            $data['tagDate'] ?? null,
            $data['tagMessage'] ?? null
        );
        return $tag;
    }

    private static function formattedOutputSchema(): Schema {
        return Schema::parse([
            'tag:s',
            'commitHash:s',
            'commitMessage:s',
            'commitDate:dt',
            'committerName:s',
            'committerEmail:s',
            'tagMessage:s?',
            'tagDate:dt?',
            'taggerName:s?',
            'taggerEmail:s?',
        ]);
    }

    ///
    /// Getters
    ///

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @return Commit
     */
    public function getCommit(): Commit {
        return $this->commit;
    }

    /**
     * @return string
     */
    public function getCommitHash(): string {
        return $this->getCommit()->getCommitHash();
    }

    /**
     * @return Author|null
     */
    public function getAuthor(): ?Author {
        return $this->author;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getDate(): ?\DateTimeInterface {
        return $this->date;
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string {
        return $this->message;
    }
}
