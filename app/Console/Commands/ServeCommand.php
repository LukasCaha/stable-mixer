<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;
use Symfony\Component\Process\Process;

class ServeCommand extends BaseServeCommand
{
    /**
     * The built-in server is a new PHP process. It does not inherit `-d` flags
     * or PHP_INI_SCAN_DIR from `php artisan serve`, so enable extensions here
     * when the default php.ini leaves them commented out.
     *
     * @return array<int, string>
     */
    protected function serverCommand()
    {
        $command = parent::serverCommand();
        $binary = array_shift($command);

        return array_merge([$binary], $this->missingExtensionDirectives(), $command);
    }

    /**
     * @return list<string>
     */
    protected function missingExtensionDirectives(): array
    {
        $loaded = $this->defaultPhpModules();
        $directives = [];

        foreach (['pdo_sqlite', 'sqlite3', 'intl'] as $extension) {
            if (in_array($extension, $loaded, true)) {
                continue;
            }

            $directives[] = '-d';
            $directives[] = 'extension='.$extension;
        }

        return $directives;
    }

    /**
     * @return list<string>
     */
    protected function defaultPhpModules(): array
    {
        $environment = getenv();
        unset($environment['PHP_INI_SCAN_DIR']);

        $process = new Process([PHP_BINARY, '-m'], null, $environment === [] ? null : $environment);
        $process->run();

        return array_values(array_filter(
            array_map(trim(...), explode("\n", $process->getOutput())),
            fn (string $line): bool => $line !== '' && ! str_starts_with($line, '['),
        ));
    }
}
