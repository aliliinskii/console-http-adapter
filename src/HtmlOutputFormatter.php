<?php
namespace ConsoleHttpAdapter;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;

class HtmlOutputFormatter implements OutputFormatterInterface
{
    /**
     * @var OutputFormatterInterface
     */
    private $outputFormatter;

    /**
     * @var AnsiToHtmlConverter
     */
    private $ansiConverter;

    /**
     * @var array
     */
    private $ansiReplacements = [
        // erasing previous line
        "\x1B[2K" => PHP_EOL,
    ];

    /**
     * @param OutputFormatterInterface $outputFormatter
     * @param AnsiToHtmlConverter $ansiConverter
     */
    public function __construct(OutputFormatterInterface $outputFormatter, AnsiToHtmlConverter $ansiConverter)
    {
        $this->outputFormatter = $outputFormatter;
        $this->ansiConverter = $ansiConverter;
    }

    /**
     * {@inheritDoc}
     * @see \Symfony\Component\Console\Formatter\OutputFormatterInterface::getStyle()
     */
    public function getStyle($name)
    {
        return $this->outputFormatter->getStyle($name);
    }

    /**
     * {@inheritDoc}
     * @see \Symfony\Component\Console\Formatter\OutputFormatterInterface::format()
     */
    public function format($message)
    {
        $message = $this->outputFormatter->format($message);
        if (!$this->isDecorated()) {
            return $message;
        }

        return $this->ansiConverter->convert(
            strtr($message, $this->ansiReplacements)
        );
    }

    /**
     * {@inheritDoc}
     * @see \Symfony\Component\Console\Formatter\OutputFormatterInterface::setStyle()
     */
    public function setStyle($name, OutputFormatterStyleInterface $style)
    {
        $this->outputFormatter->setStyle($name, $style);
    }

    /**
     * {@inheritDoc}
     * @see \Symfony\Component\Console\Formatter\OutputFormatterInterface::isDecorated()
     */
    public function isDecorated()
    {
        return $this->outputFormatter->isDecorated();
    }

    /**
     * {@inheritDoc}
     * @see \Symfony\Component\Console\Formatter\OutputFormatterInterface::hasStyle()
     */
    public function hasStyle($name)
    {
        return $this->outputFormatter->hasStyle($name);
    }

    /**
     * {@inheritDoc}
     * @see \Symfony\Component\Console\Formatter\OutputFormatterInterface::setDecorated()
     */
    public function setDecorated($decorated)
    {
        $this->outputFormatter->setDecorated($decorated);
    }
}
