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
     * @var string
     */
    private $minAvailable;

    /**
     * @var string
     */
    private $maxUnavailable;

    /**
     * @var string
     */
    private $allowedDisruptions;

    /**
     * @var string
     */
    private $age;

    /**
     * @var ConsoleOutput
     */
    private $consoleOutput;

    /**
     * @var array
     */
    private $podDisruptionBudgetInfo;

    public function __construct(
        string $namespace,
        string $name,
        string $minAvailable,
        string $maxUnavailable,
        string $allowedDisruptions,
        string $age,
        ConsoleOutput $consoleOutput
    ) {
        $this->namespace = $namespace;
        $this->name = $name;
        $this->minAvailable = $minAvailable;
        $this->maxUnavailable = $maxUnavailable;
        $this->allowedDisruptions = $allowedDisruptions;
        $this->age = $age;
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
                ['Age', $this->age],
            ]);

        $table->render();
    }

    public function getSelector(): array
    {
        $selector = $this->getPodDisruptionBudgetInfo()['spec']['selector']['matchLabels'];
        ksort($selector);
        return $selector;
    }

    private function getPodDisruptionBudgetInfo()
    {
        if ($this->podDisruptionBudgetInfo !== null){
            return $this->podDisruptionBudgetInfo;
        }

        $cacheFile = dirname(__DIR__) . '/cache/pod-disruption-budget-' . $this->name . '.cache';

        if (file_exists($cacheFile)) {
            $textPodDisruptionBudgets = file_get_contents($cacheFile);
        } else {
            $command = 'kubectl -n ' . $this->namespace . ' get pdb ' . $this->name . ' -o json';
            $textPodDisruptionBudgets = shell_exec($command);
            file_put_contents($cacheFile, $textPodDisruptionBudgets);
        }
        $this->podDisruptionBudgetInfo = json_decode($textPodDisruptionBudgets, true);
    }
}
