<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git;

/**
 * Class implementing a git remote for a repository.
 */
class Remote {

    /** @var string */
    private $name;

    /** @var string */
    private $uri;

    /** @var bool */
    private $canFetch;

    /** @var bool */
    private $canPush;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $uri
     */
    public function __construct(string $name, string $uri) {
        $this->name = $name;
        $this->uri = $uri;
    }

    /**
     * Create a remote from a line of output from `git remote -v`.
     *
     * @example Input format
     * vanilla-oss	git@github.com:vanilla/vanilla.git (fetch)
     *
     * @param string $gitOutput
     * @return Remote[]
     */
    public static function fromGitOutput(string $gitOutput): array {
        $remotesByName = [];

        $remoteLines = explode("\n", $gitOutput);
        foreach ($remoteLines as $remoteLine) {
            if (empty(trim($remoteLine))) {
                continue;
            }
            preg_match('/^([^\s]+)\s+([^\s]+)\s+\((.*)\)$/', $remoteLine, $m);
            $remoteName = trim($m[1]);
            $remoteUri = trim($m[2]);
            $remoteMode = trim($m[3]);

            $remote = $remotesByName[$remoteName] ?? new Remote($remoteName, $remoteUri);
            if ($remoteMode === 'push') {
                $remote->canPush = true;
            } elseif ($remoteMode === 'fetch') {
                $remote->canFetch = true;
            }
            $remotesByName[$remoteName] = $remote;
        }

        return array_values($remotesByName);
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getUri(): string {
        return $this->uri;
    }

    /**
     * @return bool
     */
    public function canFetch(): bool {
        return $this->canFetch;
    }

    /**
     * @return bool
     */
    public function canPush(): bool {
        return $this->canPush;
    }
}
