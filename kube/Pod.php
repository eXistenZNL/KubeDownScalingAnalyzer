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

    }

    public function getInfo(): array
    {
        $unmovableReason = $this->getUnmovableReason();

        return [
            $this->namespace,
            $this->name,
            $this->isSafeToEvict() ? 'Yes' : 'No',
            $this->getControllerType() ?? '-',
            is_null($this->getLocalVolumes()) ? 'Yes' : 'No',
            $unmovableReason === null ? 'Yes' : '<fg=red>No</>',
            $unmovableReason
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

    private function getMatchingPodDisruptionBudget(): ?PodDisruptionBudget
    {
        return null;
    }

    private function getUnmovableReason(): ?string
    {
        if ($this->namespace === 'kube-system' && $this->getMatchingPodDisruptionBudget() === null) {
            return 'In kube-system namespace without pod disruption budget';
        }

        if ($this->getControllerType() === 'Daemonset') {
            return null;
        }

        if ($this->getControllerType() === null && !$this->isSafeToEvict()) {
            return 'Not backed by a controller object and is not safe to evict';
        }

        $localVolumes = $this->getLocalVolumes();
        if (!is_null($localVolumes) && !$this->isSafeToEvict()) {
            return sprintf(
                'Has local volumes and is not safe to evict:' . PHP_EOL . '- %s',
                http_build_query($localVolumes, '', PHP_EOL . '- ')
            );
        }

        return null;
    }
}
