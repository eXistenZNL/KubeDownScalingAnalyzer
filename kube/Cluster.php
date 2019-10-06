<?php

declare(strict_types=1);

use Symfony\Component\Console\Output\ConsoleOutput;

class Cluster
{
    /**
     * @var ConsoleOutput
     */
    private $consoleOutput;

    /**
     * @var array
     */
    private $podDisruptionBudgets;

    public function __construct(ConsoleOutput $consoleOutput)
    {
        $this->consoleOutput = $consoleOutput;
    }

    public function analyze()
    {
        foreach ($this->getPodDisruptionBudgets() as $podDisruptionBudget) {
            $podDisruptionBudget->getInfo();
        }

        foreach ($this->getNodes() as $node) {
            $node->getInfo();
            foreach ($node->getPods() as $pod) {
                $pod->setPodDisruptionBudgets($this->getPodDisruptionBudgets());
                $pod->getInfo();
            }
        }
    }

    /**
     * @return Node[]
     */
    public function getNodes() : array
    {
        $cacheFile = dirname(__DIR__) . '/cache/nodes.cache';

        if (file_exists($cacheFile)) {
            $textNodes = file_get_contents($cacheFile);
        } else {
            $textNodes = shell_exec('kubectl get nodes | tail -n +2 | cut -d " " -f 1');
            file_put_contents($cacheFile, $textNodes);
        }
        $textNodes = array_filter(explode(PHP_EOL, $textNodes));

        $nodes = [];

        foreach ($textNodes as $name) {
            $node = new Node($name, $this->consoleOutput);
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @return PodDisruptionBudget[]
     */
    public function getPodDisruptionBudgets()
    {
        if (is_array($this->podDisruptionBudgets)) {
            return $this->podDisruptionBudgets;
        }

        $cacheFile = dirname(__DIR__) . '/cache/pdb.cache';

        if (file_exists($cacheFile)) {
            $textPodDisruptionBudgets = file_get_contents($cacheFile);
        } else {
            $textPodDisruptionBudgets = shell_exec('kubectl get pdb -A | tail -n +2');
            file_put_contents($cacheFile, $textPodDisruptionBudgets);
        }

        $textPodDisruptionBudgets = array_filter(explode(PHP_EOL, $textPodDisruptionBudgets), function ($value) {
            return $value === null;
        });

        $podDisruptionBudgets = [];

        foreach ($textPodDisruptionBudgets as $textPodDisruptionBudget) {
            $fields = array_values(array_filter(explode(' ', $textPodDisruptionBudget)));

            $podDisruptionBudget = new PodDisruptionBudget(
                $fields[0],
                $fields[1],
                $fields[2],
                $fields[3],
                $fields[4],
                $fields[5],
                $this->consoleOutput
            );
            $podDisruptionBudgets[] = $podDisruptionBudget;
        }

        return $podDisruptionBudgets;
    }
}
