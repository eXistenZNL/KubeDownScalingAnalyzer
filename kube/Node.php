<?php

declare(strict_types=1);

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\ConsoleOutput;

class Node
{
    private $name;

    /**
     * @var ConsoleOutput
     */
    private $consoleOutput;

    /**
     * @var Pod[]
     */
    private $pods;

    /**
     * @var PodDisruptionBudget[]
     */
    private $podDisruptionBudgets;

    public function __construct(string $name, ConsoleOutput $consoleOutput)
    {
        $this->name = $name;
        $this->consoleOutput = $consoleOutput;
    }

    public function getInfo(): void
    {
        $table = new Table($this->consoleOutput);

        $table->setHeaders([
            'Node',
            new TableCell($this->name, ['colspan' => 6])
        ])
            ->setRows([
                ['Namespace', 'Name', 'Controller', 'SafeToEvict', 'PDB', 'Local Volumes', 'Movable', 'Reason'],
                new TableSeparator()
            ]);

        foreach ($this->getPods() as $pod) {
            $pod->setPodDisruptionBudgets($this->podDisruptionBudgets);
            $table->addRow($pod->getInfo());
        }

        $table->render();
    }

    /**
     * @return Pod[]
     */
    public function getPods(): ?array
    {
        if ($this->pods !== null){
            return $this->pods;
        }

        $cacheFile = dirname(__DIR__) . '/cache/pods-' . $this->name . '.cache';

        if (file_exists($cacheFile)) {
            $textPods = file_get_contents($cacheFile);
        } else {
            $command = 'kubectl get pods -A -o wide --field-selector spec.nodeName=' . $this->name . ' | tail -n +2';
            $textPods = shell_exec($command);
            file_put_contents($cacheFile, $textPods);
        }
        $textPods = array_filter(explode(PHP_EOL, $textPods));

        $this->pods = [];

        foreach($textPods as $textPod) {
            $fields = array_values(array_filter(explode(' ', $textPod)));
            $pod = new Pod(
                $fields[0],
                $fields[1],
                $this->consoleOutput
            );
            $this->pods[] = $pod;
        }

        return $this->pods;
    }

    public function setPodDisruptionBudgets(array $podDisruptionBudgets)
    {
        $this->podDisruptionBudgets = $podDisruptionBudgets;
    }
}
