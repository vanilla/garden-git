<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git;

use Garden\Git\Exception\GitException;
use Garden\Schema\Schema;
use Garden\Schema\ValidationException;

/**
 * Represents a git commit.
 */
class Commit implements AuthorableInterface, CommitishInterace {

    /** @var string */
    private $hash;

    /** @var \DateTimeInterface */
    private $date;

    /** @var Author */
    private $author;

    /** @var string */
    private $message;

    /**
     * Constructor.
     *
     * @param string $hash
     * @param \DateTimeInterface $date
     * @param Author $author
     * @param string $message
     */
    public function __construct(string $hash, \DateTimeInterface $date, Author $author, string $message) {
        $this->hash = $hash;
        $this->date = $date;
        $this->author = $author;
        $this->message = $message;
    }

    public static function gitLogFormat(): string {
        $format = <<<FORMAT
commitHash: %h
commitMessage: %s
commitDate: %aI
committerName: %an
committerEmail: %ae
FORMAT;
        return $format;
    }

    public static function fromGitOutput(string $output): Commit {
        $data = self::extractFormattedData($output);
        try {
            $data = self::formattedOutputSchema()->validate($data);
        } catch (ValidationException $e) {
            throw new GitException($e->getMessage(), 422, $e);
        }

        $author = new Author($data['committerName'], $data['committerEmail']);
        $commit = new Commit($data['commitHash'], $data['commitDate'], $author, $data['commitMessage']);
        return $commit;
    }

    public static function extractFormattedData(string $output): array {
        $output = trim($output);
        // Split on newlines and treat each one how we would parse an http header.
        $data = [];
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }
            $splitLine = explode(": ", $line);
            if (count($splitLine) !== 2) {
                throw new GitException('Commit data should have 2 parts: ' . $line);
            }
            $fieldName = $splitLine[0] ?? null;
            $fieldValue = $splitLine[1] ?? null;
            $data[$fieldName] = trim($fieldValue);
        }
        return $data;
    }

    public static function formattedOutputSchema(): Schema {
        return Schema::parse([
            'commitHash:s',
            'commitMessage:s',
            'commitDate:dt',
            'committerName:s',
            'committerEmail:s',
        ]);
    }

    /**
     * @return string
     */
    public function getCommitish(): string {
        return $this->hash;
    }

    /**
     * @return string
     */
    public function getCommitHash(): string {
        return $this->hash;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getDate(): \DateTimeInterface {
        return $this->date;
    }

    /**
     * @return Author
     */
    public function getAuthor(): Author {
        return $this->author;
    }

    /**
     * @return string
     */
    public function getMessage(): string {
        return $this->message;
    }
}
