<?php

declare(strict_types=1);

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;

class PodDisruptionBudget
{
    /**
     * @var string
     */
    private $namespace;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $minAvailable;

    /**
     * @var int
     */
    private $maxUnavailable;

    /**
     * @var int
     */
    private $allowedDisruptions;

    /**
     * @var ConsoleOutput
     */
    private $consoleOutput;

    /**
     * @var array
     */
    private $labelSelector;

    public function __construct(
        string $namespace,
        string $name,
        int $minAvailable,
        int $maxUnavailable,
        int $allowedDisruptions,
        array $labelSelector,
        ConsoleOutput $consoleOutput
    ) {
        $this->namespace = $namespace;
        $this->name = $name;
        $this->minAvailable = $minAvailable;
        $this->maxUnavailable = $maxUnavailable;
        $this->allowedDisruptions = $allowedDisruptions;
        $this->labelSelector = $labelSelector;
        $this->consoleOutput = $consoleOutput;
    }

    public function getInfo(): void
    {
        $table = new Table($this->consoleOutput);

        $table->setHeaders(['PodDisruptionBudget', $this->name])
            ->setRows([
                ['Namespace', $this->namespace],
                ['Name', $this->name],
                ['NinAvailable', $this->minAvailable],
                ['MaxUnavailable', $this->maxUnavailable],
                ['AllowedDisruptions', $this->allowedDisruptions],
            ]);

        $table->render();
    }

    /**
     * @return array
     */
    public function getLabelSelector(): array
    {
        return $this->labelSelector;
    }

    /**
     * @return string
     */
    public function getAllowedDisruptions(): int
    {
        return $this->allowedDisruptions;
    }
}
