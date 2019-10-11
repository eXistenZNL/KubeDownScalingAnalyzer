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
            $node->setPodDisruptionBudgets($this->getPodDisruptionBudgets());
            $node->getInfo();
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
            $pdbInfoText = file_get_contents($cacheFile);
        } else {
            $pdbInfoText = shell_exec('kubectl get pdb -A -ojson');
            file_put_contents($cacheFile, $pdbInfoText);
        }

        $pdbInfoArray = json_decode($pdbInfoText, true);

        $podDisruptionBudgets = [];

        foreach ($pdbInfoArray['items'] as $pdbInfo) {
            $podDisruptionBudget = new PodDisruptionBudget(
                $pdbInfo['metadata']['namespace'],
                $pdbInfo['metadata']['name'],
                $pdbInfo['spec']['minAvailable'] ?? 0,
                $pdbInfo['spec']['maxUnavailable'] ?? 0,
                $pdbInfo['status']['disruptionsAllowed'],
                $pdbInfo['spec']['selector']['matchLabels'],
                $this->consoleOutput
            );
            $podDisruptionBudgets[] = $podDisruptionBudget;
        }

        $this->podDisruptionBudgets = $podDisruptionBudgets;
        return $podDisruptionBudgets;
    }
}
