<?php

namespace hexa_package_instagram\Console;

use hexa_package_instagram\Support\InstagramUsernameExtractor;
use Illuminate\Console\Command;

class InstagramExtractUsernamesCommand extends Command
{
    protected $signature = 'instagram:extract-usernames
        {file : Text or HTML file to read (a pasted dump)}
        {--exclude=* : Usernames to leave out (for example the list owner)}
        {--no-mentions : Ignore plain "@name" text; only take profile links}
        {--json : Print a JSON array}';

    protected $description = 'List every Instagram username found in a pasted dump (HTML of a following list, profile links or @names), each once.';

    /**
     * Print one username per line, or a JSON array.
     */
    public function handle(InstagramUsernameExtractor $extractor): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path)) {
            $this->error('File not found: '.$path);

            return self::FAILURE;
        }
        $names = $extractor->extract((string) file_get_contents($path), (array) $this->option('exclude'), ! $this->option('no-mentions'));
        $this->line($this->option('json') ? json_encode($names) : implode("\n", $names));

        return self::SUCCESS;
    }
}
