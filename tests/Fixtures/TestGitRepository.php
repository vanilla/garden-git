<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Tests\Fixtures;

use Garden\Git\Repository;

/**
 * Fixture for test git repositories.
 */
class TestGitRepository extends Repository {

    /** @var TestGitRepository|null */
    private static $lastUsedInstance;

    /** @var string|null */
    private $lastGitOutput;

    /** @var string|null */
    private $lastGitCommand;

    /**
     * Track some extra data while running.
     *
     * @param array $args
     *
     * @return string
     */
    public function git(array $args): string {
        self::$lastUsedInstance = $this;
        $this->lastGitCommand = implode(' ', $args);
        $output = parent::git($args);
        $this->lastGitOutput = $output;
        return $output;
    }

    /**
     * Get extra info for debugging in exceptions.
     *
     * @return string
     */
    public static function getExceptionDebugMessage(): string {
        $instance = self::$lastUsedInstance;
        if ($instance === null || $instance->lastGitOutput === null || $instance->lastGitCommand === null) {
            return '';
        }

        $details = [
            "Git Binary" => $instance->gitPath,
            "Working Directory" => $instance->dir,
            "Last Command" => $instance->lastGitCommand,
            "Last Output" => $instance->lastGitOutput,
        ];

        $message = "";
        foreach ($details as $name => $detail) {
            $message .= "\n\n$name\n------------------------\n$detail";
        }

        return $message;
    }
}
