# Kubernetes cluster downscaling analyzer

[![Docker Build Status](https://img.shields.io/travis/eXistenZNL/KubeDownScalingAnalyzer.svg?style=flat-square)](https://travis-ci.org/eXistenZNL/KubeDownScalingAnalyzer) [![Docker Pulls](https://img.shields.io/docker/pulls/existenz/kubedownscalinganalyzer.svg?style=flat-square)](https://hub.docker.com/r/existenz/kubedownscalinganalyzer/) [![License](https://img.shields.io/github/license/existenznl/kubedownscalinganalyzer.svg?style=flat-square)](https://github.com/eXistenZNL/KubeDownScalingAnalyzer/blob/master/LICENSE)

## About

One of the tougher things of maintaining a Kubernetes cluster is making sure it scales down correctly. 
Even the official Kubernetes autoscaler FAQ has [a section](https://github.com/kubernetes/autoscaler/blob/master/cluster-autoscaler/FAQ.md#what-types-of-pods-can-prevent-ca-from-removing-a-node) specifically targeted to this.

This tool helps identifying the issues that the pods in your cluster may have that prevent the cluster autoscaler from scaling down.   

## How can I use it?

### From source

1. Make sure you are connected to the right cluster with kubectl.
1. Clone this project and run `php analyzer.php`

### Using the Docker container

Coming soon.

## Bugs, questions, and improvements

If you found a bug or have a question, please open an issue on the GitHub Issue tracker.
Improvements can be sent by a Pull Request against the master branch and are greatly appreciated!
