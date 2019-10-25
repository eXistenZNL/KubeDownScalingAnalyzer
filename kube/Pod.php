<?php

declare(strict_types=1);

use Symfony\Component\Console\Output\ConsoleOutput;

class Pod
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
     * @var ConsoleOutput
     */
    private $consoleOutput;

    /**
     * @var array
     */
    private $podInfo;

    /**
     * @var PodDisruptionBudget[]
     */
    private $podDisruptionBudgets;

    /**
     * @var PodDisruptionBudget|false
     */
    private $matchingPodDisruptionBudget;

    public function __construct(
        string $namespace,
        string $name,
        ConsoleOutput $consoleOutput
    ) {
        $this->namespace = $namespace;
        $this->name = $name;
        $this->consoleOutput = $consoleOutput;
    }

    public function setPodDisruptionBudgets(array $podDisruptionBudgets)
    {
        $this->podDisruptionBudgets = $podDisruptionBudgets;
    }

    public function getInfo(): array
    {
        $unmovableReason = $this->getUnmovableReason();

        $controllerType = $this->getControllerType();
        if ($this->getControllerType() === 'Job') {
            $controllerType .= '(' . $this->getPodInfo()['status']['phase'] . ')';
        }

        return [
            $this->namespace,
            $this->name,
            $controllerType ?? '-',
            $this->isSafeToEvict() ? 'Yes' : 'No',
            $this->getMatchingPodDisruptionBudget() instanceof PodDisruptionBudget ? 'Yes' : 'No',
            is_null($this->getLocalVolumes()) ? 'No' : 'Yes',
            $unmovableReason === null ? '<fg=green>Yes</>' : '<fg=red>No</>',
            $unmovableReason,
        ];
    }

    public function getPodInfo()
    {
        if (is_array($this->podInfo)) {
            return $this->podInfo;
        }

        $cacheFile = dirname(__DIR__) . '/cache/pod-' . $this->name . '.cache';

        if (file_exists($cacheFile)) {
            $this->podInfo = unserialize(file_get_contents($cacheFile));
            return $this->podInfo;
        }

        $command = sprintf(
            'kubectl -n %s get pod %s -ojson',
            $this->namespace,
            $this->name
        );

        $this->podInfo = json_decode(shell_exec($command), true);
        file_put_contents($cacheFile, serialize($this->podInfo));

        return $this->podInfo;
    }

    private function getUnmovableReason(): ?string
    {
        if ($this->getControllerType() === 'DaemonSet') {
            return null;
        }

        if ($this->getControllerType() === 'Job' && $this->getStatusPhase() !== 'Running') {
            return null;
        }

        $podDisruptionBudget = $this->getMatchingPodDisruptionBudget();

        if ($this->namespace === 'kube-system' && $podDisruptionBudget === null) {
            return 'In kube-system namespace but without a pod disruption budget';
        }

        if ($podDisruptionBudget instanceof PodDisruptionBudget && $podDisruptionBudget->getAllowedDisruptions() === "0") {
            return 'No allowed disruptions in pod disruption budget';
        }

        if ($this->getControllerType() === null && !$this->isSafeToEvict()) {
            return 'Not backed by a controller object and is not safe to evict';
        }

        $localVolumes = $this->getLocalVolumes();
        if (!is_null($localVolumes) && !$this->isSafeToEvict()) {
            return 'Has local volumes and is not safe to evict';
        }

        return null;
    }

    private function getControllerType(): ?string
    {
        if (!isset($this->getPodInfo()['metadata']['ownerReferences'][0])) {
            return null;
        }

        return $this->getPodInfo()['metadata']['ownerReferences'][0]['kind'];
    }

    private function isSafeToEvict(): bool
    {
        if (!isset($this->getPodInfo()['metadata']['annotations']['cluster-autoscaler.kubernetes.io/safe-to-evict'])) {
            return false;
        }

        if ('true' !== $this->getPodInfo()['metadata']['annotations']['cluster-autoscaler.kubernetes.io/safe-to-evict']) {
            return false;
        }

        return true;
    }

    private function getLabels(): ?array
    {
        $labels = $this->getPodInfo()['metadata']['labels'];
        ksort($labels);
        return $labels;
    }

    private function getLocalVolumes(): ?array
    {
        if (!isset($this->getPodInfo()['spec']['volumes'])) {
            return null;
        }

        $volumes = [];

        foreach ($this->getPodInfo()['spec']['volumes'] as $volume) {
            if (array_key_exists('emptyDir', $volume)) {
                $volumes[$volume['name']] = 'emptyDir';
            }
            if (array_key_exists('local', $volume)) {
                $volumes[$volume['name']] = 'local';
            }
        }

        return count($volumes) > 0 ? $volumes : null;
    }

    private function getStatusPhase(): string
    {
        return $this->getPodInfo()['status']['phase'];
    }

    private function getMatchingPodDisruptionBudget(): ?PodDisruptionBudget
    {
        if ($this->matchingPodDisruptionBudget instanceof PodDisruptionBudget) {
            return $this->matchingPodDisruptionBudget;
        }

        if ($this->matchingPodDisruptionBudget === false) {
            return null;
        }

        foreach ($this->podDisruptionBudgets as $podDisruptionBudget) {
            if (count(array_diff($podDisruptionBudget->getLabelSelector(), $this->getLabels())) === 0) {
                $this->matchingPodDisruptionBudget = $podDisruptionBudget;
                return $this->matchingPodDisruptionBudget;
            }
        }

        $this->matchingPodDisruptionBudget = false;

        return null;
    }
}
