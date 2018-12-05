<?php
namespace ConsoleHttpAdapter;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Application;
use SensioLabs\AnsiConverter\Theme\Theme;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class ConsoleHttpAdapter
{
    /**
     * @var string
     */
    private $charset = 'UTF-8';

    /**
     * @var array
     */
    private $implicitBufferFlushIniList = [
        'implicit_flush' => 'on',
        'zlib.output_compression' => 'off',
    ];

    /**
     * @var AnsiToHtmlConverter
     */
    private $ansiConverter;

    /**
     * @param Theme $theme
     */
    public function __construct(Theme $theme = null)
    {
        $this->ansiConverter = new AnsiToHtmlConverter($theme, false, $this->charset);
    }

    /**
     * @param string $ini
     * @param string $value
     * @throws \RuntimeException
     * @return string
     */
    private function iniSet(string $ini, string $value): string
    {
        $previous = ini_set($ini, $value);
        if ($previous === false) {
            throw new \RuntimeException(sprintf('Unable to change ini `%s` to `%s`', $ini, $value));
        }

        return $previous;
    }

    /**
     * @return array
     */
    private function setupIni(): array
    {
        $previousIniValues = [];
        foreach ($this->implicitBufferFlushIniList as $ini => $value) {
            try {
                $previousIniValues[$ini] = $this->iniSet($ini, $value);
            } catch (\Throwable $ex) {
                $this->restoreIni($previousIniValues);
                throw $ex;
            }
        }

        return $previousIniValues;
    }

    /**
     * @param array $iniValues
     */
    private function restoreIni(array $iniValues)
    {
        $outputDependentIniList = ['zlib.output_compression'];
        foreach ($iniValues as $ini => $value) {
            if (!(in_array($ini, $outputDependentIniList) && headers_sent())) {
                $this->iniSet($ini, $value);
            }
        }
    }

    private function emitHeaders()
    {
        header('X-Accel-Buffering: no');
        header('Content-Type: text/html; charset='.$this->charset);
        header('Cache-Control: no-cache, must-revalidate');
    }

    private function setupOutputBuffer()
    {
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    /**
     * @param callable $callable
     */
    private function trapEnv(callable $callable)
    {
        $iniList = $this->setupIni();
        try {
            $callable();
        } finally {
            $this->restoreIni($iniList);
        }
    }

    /**
     * @param Application $application
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function run(Application $application, InputInterface $input, OutputInterface $output = null)
    {
        $this->trapEnv(
            function() use ($application, $input, $output) {
                $this->emitHeaders();
                $this->setupOutputBuffer();
                $this->runApplication($application, $input, $output);
            }
        );
    }

    /**
     * @param Application $application
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function runApplication(Application $application, InputInterface $input, OutputInterface $output = null)
    {
        $output = $this->configureOutput($output ?? $this->createDefaultOutput(), $input);
        $application->setAutoExit(false);
        $this->startOutput($output);
        try {
            $application->run($input, $output);
        } finally {
            $this->closeOutput($output);
        }
    }

    /**
     * @throws \RuntimeException
     * @return OutputInterface
     */
    private function createDefaultOutput(): OutputInterface
    {
        $outputStream = fopen('php://output', 'w');
        if ($outputStream === false) {
            throw new \RuntimeException('Unable to open output stream for writing');
        }

        return new StreamOutput($outputStream, OutputInterface::VERBOSITY_NORMAL, true);
    }

    /**
     * @param OutputInterface $output
     * @param InputInterface $input
     * @return OutputInterface
     */
    private function configureOutput(OutputInterface $output, InputInterface $input): OutputInterface
    {
        if ($input->hasParameterOption('--ansi', true)) {
            $output->setDecorated(true);
        } elseif ($input->hasParameterOption('--no-ansi', true)) {
            $output->setDecorated(false);
        }

        if ($output->isDecorated()) {
            $output->setFormatter(
                new HtmlOutputFormatter($output->getFormatter(), $this->ansiConverter)
            );
        }

        return $output;
    }

    /**
     * @param OutputInterface $output
     */
    private function startOutput(OutputInterface $output)
    {
        $head = <<<HEAD
<!DOCTYPE html>
<html>
<body style="margin: 0;">
<style>
%s
</style>
<pre class="%s" style="font-size: 1.3em; padding: 0.5vh 0.5%%; margin: 0; min-height: 99vh; display: inline-block; min-width: 99%%;">
HEAD;
        $output->writeln(
            sprintf(
                $head,
                $output->isDecorated() ? $this->ansiConverter->getTheme()->asCss() : '',
                $output->isDecorated() ? 'ansi_color_bg_black' : ''
            ),
            OutputInterface::OUTPUT_RAW
        );
    }

    /**
     * @param OutputInterface $output
     */
    private function closeOutput(OutputInterface $output)
    {
        $output->writeln('</pre></body></html>', OutputInterface::OUTPUT_RAW);
    }
}
