<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Serve extends BaseServeCommand
{
    /**
     * Command description.
     *
     * @var string
     */
    protected $description = 'Serve the application on the PHP development server (default: 0.0.0.0:8013)';

    /**
     * Initialize and set sensible defaults for host and port while preserving
     * the base command's options (so flags like --no-reload remain available).
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // If host option not supplied, set to 0.0.0.0
        if ($input->getParameterOption(['--host', '-h'], false) === false) {
            $input->setOption('host', '0.0.0.0');
        }

        // If port option not supplied, set to 8013
        if ($input->getParameterOption(['--port'], false) === false) {
            $input->setOption('port', '8013');
        }

        parent::initialize($input, $output);
    }

    /**
     * Pass upload limits to the PHP built-in server before request startup.
     *
     * @return list<string>
     */
    protected function serverCommand(): array
    {
        $command = parent::serverCommand();
        $maxFileMb = (int) config('mhcs.upload.max_file_mb');
        $maxRequestMb = (int) ceil(((int) config('mhcs.upload.max_request_bytes')) / (1024 * 1024));

        return [
            $command[0],
            '-d',
            'post_max_size='.$maxRequestMb.'M',
            '-d',
            'upload_max_filesize='.$maxFileMb.'M',
            ...array_slice($command, 1),
        ];
    }
}
